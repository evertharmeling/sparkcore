<?php

namespace SparkCore\Stream;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SparkCore\Exception\ConnectionLimitExceededException;
use SparkCore\Exception\SparkCoreException;
use SparkCore\Stream\Event\SparkEvent;
use SparkCore\Stream\Event\SparkEventInterface;

/**
 * Sparkhose class makes it easy to connect and consume the Spark SSE stream
 *
 * @author Evert Harmeling <evertharmeling@hotmail.com>
 */
abstract class Sparkhose
{
    const SCHEME    = 'ssl://';
    const HOSTNAME  = 'api.spark.io';
    const PORT      = 443;
    const URL_PATH  = '/v1/events';

    /** @var string */
    protected $deviceId;

    /** @var string */
    protected $accessToken;

    /** @var LoggerInterface */
    protected $logger;

    /** @var resource */
    protected $conn;

    /** @var boolean */
    protected $reconnect = true;

    /** @var string */
    protected $userAgent = 'Sparkhose v1.0';

    /** @var int */
    protected $readTimeout = 5;

    /** @var int */
    protected $connectTimeout = 5;

    /** @var int */
    protected $idleReconnectTimeout = 90;

    /** @var int */
    protected $connectFailuresMax = 20;

    /** @var int */
    protected $enqueueSpent = 0;

    /** @var string */
    protected $buff;

    /** @var array */
    protected $fdrPool;

    /** @var int */
    protected $lastErrorNo;

    /** @var string */
    protected $lastErrorMsg;

    /** @var string */
    protected $eventName;

    /** @var SparkEventInterface */
    private $eventInstance;

    public function __construct($deviceId, $accessToken, SparkEventInterface $event = null)
    {
        $this->deviceId = $deviceId;
        $this->accessToken = $accessToken;

        if (is_null($event)) {
            $this->eventInstance = new SparkEvent();
        } else {
            $this->eventInstance = $event;
        }
    }

    /**
     * @param  string    $accessToken
     * @return Sparkhose
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @param  string    $deviceId
     * @return Sparkhose
     */
    public function setDeviceId($deviceId)
    {
        $this->deviceId = $deviceId;

        return $this;
    }

    /**
     * @return string
     */
    public function getDeviceId()
    {
        return $this->deviceId;
    }

    /**
     * @param  LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if ($this->logger) {
            return $this->logger;
        }

        return $this->logger = new NullLogger();
    }

    /**
     * Set a specific event name to listen to, otherwise all public events will be parsed
     *
     * @param  string    $eventName
     * @return Sparkhose
     */
    public function setEventName($eventName)
    {
        $this->eventName = $eventName;

        return $this;
    }

    /**
     * @return string
     */
    public function getEventName()
    {
        return $this->eventName;
    }

    /**
     * @return string
     */
    protected function getRemoteSocket()
    {
        return static::SCHEME . static::HOSTNAME . ':' . static::PORT;
    }

    /**
     * @return string
     */
    protected function getUrlPath()
    {
        if ($this->getEventName()) {
            $eventPath = '/' . $this->getEventName();
        } else {
            $eventPath = '';
        }

        return static::URL_PATH . $eventPath;
    }

    /**
     * Creates a connection to the stream
     *
     * @throws ConnectionLimitExceededException
     * @throws SparkCoreException
     */
    public function connect()
    {
        $httpCode = 0;
        $connectFailures = 0;
        $errNo = 0;
        $errStr = '';

        do {
            if (!$this->conn = stream_socket_client($this->getRemoteSocket(), $errNo, $errStr)) {
                $this->logger->critical("Can't connect to '{remoteSocket}', {errNo}: {errStr}", [
                    'remoteSocket' => $this->getRemoteSocket(),
                    'errNo' => $errNo,
                    'errStr' => $errStr
                ]);
            } else {
                $this->logger->info("Connection established to '{remoteSocket}'", [
                    'remoteSocket' => $this->getRemoteSocket()
                ]);

                stream_set_blocking($this->conn, 1);

                $headers  = sprintf("GET %s HTTP/1.1\r\n", $this->getUrlPath());
                $headers .= sprintf("Host: %s\r\n", static::HOSTNAME);
                $headers .= "Accept: */*\r\n";
                $headers .= sprintf("Authorization: Bearer %s\r\n", $this->getAccessToken());
                $headers .= sprintf("User-Agent: %s\r\n", $this->userAgent);
                $headers .= "\r\n";

                fwrite($this->conn, $headers);
                $this->logger->info($headers);

                list($httpVer, $httpCode, $httpMessage) = preg_split('/\s+/', trim(fgets($this->conn, 1024)), 3);
                $this->logger->info('{httpVer} {httpCode} {httpMessage}', [
                    'httpVer' => $httpVer,
                    'httpCode' => $httpCode,
                    'httpMessage' => $httpMessage,
                ]);

                $responseHeaders = '';
                $isChunking = false;

                while ($headerLine = trim(fgets($this->conn, 4096))) {
                    $responseHeaders .= $headerLine . "\n";
                    if (strtolower($headerLine) == 'transfer-encoding: chunked') {
                        $isChunking = true;
                    }
                }

                // If we got a non-200 response, we need to backoff and retry
                if ($httpCode != 200) {
                    $connectFailures++;
                    $respBody = '';

                    while ($bLine = trim(fgets($this->conn, 4096))) {
                        $respBody .= $bLine;
                    }

                    // Set last error state
                    $this->lastErrorMsg = sprintf('HTTP ERROR %d: %s (%s)', $httpCode, $httpMessage, $respBody);
                    $this->lastErrorNo = $httpCode;

                    if ($connectFailures > $this->connectFailuresMax) {
                        $msg = sprintf("Connection failure limit exceeded with %d failures. Last error: %s", $connectFailures, $this->lastErrorMsg);
                        $this->logger->critical($msg);

                        throw new ConnectionLimitExceededException($msg, $httpCode);
                    }
                } elseif (!$isChunking) {
                    throw new SparkCoreException(sprintf("SparkCore did not send a chunking header. Is this really HTTP/1.1? Here are the headers:\n %s", $responseHeaders));
                }
            }

        } while (!is_resource($this->conn) || $httpCode != 200);

        $this->reset();
    }

    /**
     * Performs a reconnect to the stream
     *
     * @return void
     */
    protected function reconnect()
    {
        $reconnect = $this->reconnect;
        $this->disconnect(); // Implicitly sets reconnect to FALSE
        $this->reconnect = $reconnect; // Restore state to prev
        $this->connect();

        if (count($this->fdrPool) === 0) {
            $this->logger->notice('No stream available, sleeping for 10 seconds...');

            sleep(10);
            $this->reconnect();
        }
    }

    /**
     * Performs forcible disconnect from stream (if connected) and cleanup.
     *
     * @return void
     */
    protected function disconnect()
    {
        if (is_resource($this->conn)) {
            $this->logger->info('Closing Sparkhose connection.');

            fclose($this->conn);
        }

        $this->conn = null;
        $this->reconnect = false;
    }

    /**
     * Resets the buffer variables
     *
     * @return void
     */
    protected function reset()
    {
        // Connection OK, reset connect failures
        $this->lastErrorMsg = null;
        $this->lastErrorNo = null;

        stream_set_blocking($this->conn, 0);

        // Flush stream buffer & (re)assign fdrPool (for reconnect)
        $this->fdrPool = [ $this->conn ];
        $this->buff = '';
    }

    /**
     * Consumes the Server Side Events stream, each published event becomes available in the enqueueEvent function
     *
     * @return void
     */
    public function consume()
    {
        $event = clone $this->eventInstance;
        // Loop indefinitely based on reconnect
        do {
            $this->reconnect();

            $lastStreamActivity = new \DateTime();
            $fdw = $fde = null;

            while (
                !is_null($this->conn) &&
                !feof($this->conn) &&
                count($this->fdrPool) > 0 &&
                ($numChanged = stream_select($this->fdrPool, $fdw, $fde, $this->readTimeout)) !== false
            ) {
                if (((new \DateTime())->getTimestamp() - $lastStreamActivity->getTimestamp()) > $this->idleReconnectTimeout) {
                    $this->logger->notice('Idle timeout: No stream activity for > {idleReconnectTimeout} seconds. Reconnecting...', [
                        'idleReconnectTimeout' => $this->idleReconnectTimeout
                    ]);

                    $this->reconnect();
                    $lastStreamActivity = new \DateTime();
                    continue;
                }

                if (($this->buff = trim(fgets($this->conn))) == '') {
                    continue;
                }

                if (strpos($this->buff, 'event: ') !== false) {
                    $event->setName(substr($this->buff, 7));
                    $this->logger->info("Event '{eventName}' received", ['eventName' => $event->getName()]);
                }

                if (strpos($this->buff, 'data: ') !== false) {
                    $event->parseRawData(json_decode(substr($this->buff, 6)));
                    $this->logger->info("Data for event '{eventName}' received", ['eventName' => $event->getName()]);
                    $this->logger->debug("Data: {eventData}", ['eventData' => json_encode($event->getData())]);
                }

                if ($event->isValid()) {
                    $this->logger->info('Enqueuing event');

                    $enqueueStart = microtime(true);
                    $event->setCreatedAt(new \DateTime());
                    $this->enqueueEvent($event);
                    $this->enqueueSpent += (microtime(true) - $enqueueStart);

                    $event = clone $this->eventInstance;
                }

            }
        } while ($this->reconnect);

        $this->disconnect();
    }

    /**
     * This is the one and only method that must be implemented additionally. As per good practice,
     * events should NOT be processed within the same process that is performing collection
     *
     * @param SparkEventInterface $event
     */
    abstract public function enqueueEvent(SparkEventInterface $event);
}
