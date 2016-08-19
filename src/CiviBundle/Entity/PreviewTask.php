<?php

namespace CiviBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PreviewTask
 *
 * @ORM\Table(name="preview_task")
 * @ORM\Entity
 */
class PreviewTask
{
    /**
     * @var string
     *
     * @ORM\Column(name="user", type="string", length=63, nullable=false)
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="batch", type="string", length=63, nullable=false)
     */
    private $batch;

    /**
     * @var string
     *
     * @ORM\Column(name="renderer", type="string", length=63, nullable=false)
     */
    private $renderer;

    /**
     * @var string
     *
     * @ORM\Column(name="options", type="text", length=65535, nullable=false)
     */
    private $options;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="create_time", type="datetime", nullable=false)
     */
    private $createTime = 'CURRENT_TIMESTAMP';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="claim_time", type="datetime", nullable=false)
     */
    private $claimTime = '0000-00-00 00:00:00';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="finish_time", type="datetime", nullable=false)
     */
    private $finishTime = '0000-00-00 00:00:00';

    /**
     * @var integer
     *
     * @ORM\Column(name="attempts", type="integer", nullable=false)
     */
    private $attempts;

    /**
     * @var string
     *
     * @ORM\Column(name="error_message", type="text", length=65535, nullable=false)
     */
    private $errorMessage;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;



    /**
     * Set user
     *
     * @param string $user
     *
     * @return PreviewTask
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
     * @return PreviewTask
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

    /**
     * Set renderer
     *
     * @param string $renderer
     *
     * @return PreviewTask
     */
    public function setRenderer($renderer)
    {
        $this->renderer = $renderer;

        return $this;
    }

    /**
     * Get renderer
     *
     * @return string
     */
    public function getRenderer()
    {
        return $this->renderer;
    }

    /**
     * Set options
     *
     * @param string $options
     *
     * @return PreviewTask
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get options
     *
     * @return string
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set createTime
     *
     * @param \DateTime $createTime
     *
     * @return PreviewTask
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
     * Set claimTime
     *
     * @param \DateTime $claimTime
     *
     * @return PreviewTask
     */
    public function setClaimTime($claimTime)
    {
        $this->claimTime = $claimTime;

        return $this;
    }

    /**
     * Get claimTime
     *
     * @return \DateTime
     */
    public function getClaimTime()
    {
        return $this->claimTime;
    }

    /**
     * Set finishTime
     *
     * @param \DateTime $finishTime
     *
     * @return PreviewTask
     */
    public function setFinishTime($finishTime)
    {
        $this->finishTime = $finishTime;

        return $this;
    }

    /**
     * Get finishTime
     *
     * @return \DateTime
     */
    public function getFinishTime()
    {
        return $this->finishTime;
    }

    /**
     * Set attempts
     *
     * @param integer $attempts
     *
     * @return PreviewTask
     */
    public function setAttempts($attempts)
    {
        $this->attempts = $attempts;

        return $this;
    }

    /**
     * Get attempts
     *
     * @return integer
     */
    public function getAttempts()
    {
        return $this->attempts;
    }

    /**
     * Set errorMessage
     *
     * @param string $errorMessage
     *
     * @return PreviewTask
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * Get errorMessage
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }
}
