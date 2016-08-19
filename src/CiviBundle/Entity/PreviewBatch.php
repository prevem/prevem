<?php

namespace CiviBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PreviewBatch
 *
 * @ORM\Table(name="preview_batch")
 * @ORM\Entity
 */
class PreviewBatch
{
    /**
     * @var string
     *
     * @ORM\Column(name="message", type="text", length=65535, nullable=false)
     */
    private $message;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="create_time", type="datetime", nullable=false)
     */
    private $createTime = 'CURRENT_TIMESTAMP';

    /**
     * @var string
     *
     * @ORM\Column(name="user", type="string", length=63)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="batch", type="string", length=63)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $batch;



    /**
     * Set message
     *
     * @param string $message
     *
     * @return PreviewBatch
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Get message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set createTime
     *
     * @param \DateTime $createTime
     *
     * @return PreviewBatch
     */
    public function setCreateTime($createTime)
    {
        $this->createTime = $createTime;

        return $this;
    }

    /**
     * Get createTime
     *
     * @return \DateTime
     */
    public function getCreateTime()
    {
        return $this->createTime;
    }

    /**
     * Set user
     *
     * @param string $user
     *
     * @return PreviewBatch
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set batch
     *
     * @param string $batch
     *
     * @return PreviewBatch
     */
    public function setBatch($batch)
    {
        $this->batch = $batch;

        return $this;
    }

    /**
     * Get batch
     *
     * @return string
     */
    public function getBatch()
    {
        return $this->batch;
    }
}
