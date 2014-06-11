<?php

namespace SparkCore\Stream\Event;

/**
 * Interface SparkEventInterface
 *
 * @author  Evert Harmeling <evertharmeling@hotmail.com>
 */
interface SparkEventInterface
{
    /**
     * @param  string              $name
     * @return SparkEventInterface
     */
    public function setName($name);

    /**
     * @return string
     */
    public function getName();

    /**
     * @param  \DateTime           $publishedAt
     * @return SparkEventInterface
     */
    public function setPublishedAt($publishedAt);

    /**
     * @return \DateTime
     */
    public function getPublishedAt();

    /**
     * @param  \DateTime           $createdAt
     * @return SparkEventInterface
     */
    public function setCreatedAt($createdAt);

    /**
     * @return \DateTime
     */
    public function getCreatedAt();

    /**
     * @param  int                 $ttl
     * @return SparkEventInterface
     */
    public function setTtl($ttl);

    /**
     * @return int
     */
    public function getTtl();

    /**
     * @param  string              $sparkCoreId
     * @return SparkEventInterface
     */
    public function setSparkCoreId($sparkCoreId);

    /**
     * @return string
     */
    public function getSparkCoreId();

    /**
     * @param  object              $data
     * @return SparkEventInterface
     */
    public function setData($data);

    /**
     * @return object
     */
    public function getData();

    /**
     * Returns true if event holds the necessary data
     *
     * @return bool
     */
    public function isValid();

    /**
     * Parses the RAW Spark event data
     *
     * @param $data
     */
    public function parseRawData($data);
}
