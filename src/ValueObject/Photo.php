<?php

namespace App\ValueObject;

class Photo implements \JsonSerializable
{
    /** @var string */
    protected $id;

    /** @var string */
    protected $title;

    /** @var string */
    protected $image;

    /** @var string */
    protected $link;

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'image' => $this->getImage(),
            'link' => $this->getLink(),
        ];
    }


    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     *
     * @return self
     */
    public function setId(string $id): Photo
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
     * @return self
     */
    public function setTitle(string $title): Photo
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getImage(): string
    {
        return $this->image;
    }

    /**
     * @param string $image
     *
     * @return self
     */
    public function setImage(string $image): Photo
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return string
     */
    public function getLink(): string
    {
        return $this->link;
    }

    /**
     * @param string $link
     *
     * @return self
     */
    public function setLink(string $link): Photo
    {
        $this->link = $link;

        return $this;
    }
}
