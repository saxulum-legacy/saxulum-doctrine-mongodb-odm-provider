<?php

namespace Saxulum\Tests\DoctrineMongoDbOdm\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document
 */
class Page
{
    /**
     * @var string
     * @ODM\Id
     */
    protected $id;

    /**
     * @var string
     * @ODM\String
     */
    protected $title;

    /**
     * @var string
     * @ODM\String
     */
    protected $body;

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }
}
