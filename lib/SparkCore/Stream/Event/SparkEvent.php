<?php

namespace SparkCore\Stream\Event;

/**
 * SparkEvent
 *
 * @author Evert Harmeling <evertharmeling@hotmail.com>
 */
class SparkEvent implements SparkEventInterface
{
    /** @var string */
    protected $name;

    /** @var \DateTime */
    protected $createdAt;

    /** @var \DateTime */
    protected $publishedAt;

    /** @var int */
    protected $ttl;

    /** @var string */
    protected $sparkCoreId;

    /** @var object */
    protected $data;

    /**
     * @param  string     $name
     * @return SparkEvent
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param  \DateTime  $createdAt
     * @return SparkEvent
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param string      $publishedAt
     * @return SparkEvent
     */
    public function setPublishedAt($publishedAt)
    {
        $this->publishedAt = new \DateTime($publishedAt);

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getPublishedAt()
    {
        return $this->publishedAt;
    }

    /**
     * @param  int        $ttl
     * @return SparkEvent
     */
    public function setTtl($ttl)
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * @return int
     */
    public function getTtl()
    {
        return $this->ttl;
    }

    /**
     * @param  string     $sparkCoreId
     * @return SparkEvent
     */
    public function setSparkCoreId($sparkCoreId)
    {
        $this->sparkCoreId = $sparkCoreId;

        return $this;
    }

    /**
     * @return string
     */
    public function getSparkCoreId()
    {
        return $this->sparkCoreId;
    }

    /**
     * @param  object     $data
     * @return SparkEvent
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return object
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Returns true if event holds the necessary data
     *
     * @return bool
     */
    public function isValid()
    {
        return (bool) $this->getName() && $this->getPublishedAt() && $this->getSparkCoreId();
    }

    /**
     *
     * Parses the RAW Spark event data
     * @param $data
     */
    public function parseRawData($data)
    {
        if (is_object($data)) {
            $this
                ->setPublishedAt($data->published_at)
                ->setTtl($data->ttl)
                ->setSparkCoreId($data->coreid)
                ->setData(json_decode($data->data))
            ;
        }
    }
}
