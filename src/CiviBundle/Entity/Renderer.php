<?php

namespace CiviBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Renderer
 *
 * @ORM\Table(name="renderer")
 * @ORM\Entity
 */
class Renderer
{
    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=255, nullable=false)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="os", type="string", length=63, nullable=false)
     */
    private $os;

    /**
     * @var string
     *
     * @ORM\Column(name="os_version", type="string", length=63, nullable=false)
     */
    private $osVersion;

    /**
     * @var string
     *
     * @ORM\Column(name="app", type="string", length=63, nullable=false)
     */
    private $app;

    /**
     * @var string
     *
     * @ORM\Column(name="app_version", type="string", length=63, nullable=false)
     */
    private $appVersion;

    /**
     * @var array
     *
     * @ORM\Column(name="icons", type="json_array")
     */
    private $icons;

    /**
     * @var array
     *
     * @ORM\Column(name="options", type="json_array")
     */
    private $options;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_seen", type="datetime", nullable=false)
     */
    private $lastSeen = 'CURRENT_TIMESTAMP';

    /**
     * @var string
     *
     * @ORM\Column(name="renderer", type="string", length=63)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $renderer;



    /**
     * Set title
     *
     * @param string $title
     *
     * @return Renderer
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set os
     *
     * @param string $os
     *
     * @return Renderer
     */
    public function setOs($os)
    {
        $this->os = $os;

        return $this;
    }

    /**
     * Get os
     *
     * @return string
     */
    public function getOs()
    {
        return $this->os;
    }

    /**
     * Set osVersion
     *
     * @param string $osVersion
     *
     * @return Renderer
     */
    public function setOsVersion($osVersion)
    {
        $this->osVersion = $osVersion;

        return $this;
    }

    /**
     * Get osVersion
     *
     * @return string
     */
    public function getOsVersion()
    {
        return $this->osVersion;
    }

    /**
     * Set app
     *
     * @param string $app
     *
     * @return Renderer
     */
    public function setApp($app)
    {
        $this->app = $app;

        return $this;
    }

    /**
     * Get app
     *
     * @return string
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * Set appVersion
     *
     * @param string $appVersion
     *
     * @return Renderer
     */
    public function setAppVersion($appVersion)
    {
        $this->appVersion = $appVersion;

        return $this;
    }

    /**
     * Get appVersion
     *
     * @return string
     */
    public function getAppVersion()
    {
        return $this->appVersion;
    }

    /**
     * Set icons
     *
     * @param string $icons
     *
     * @return Renderer
     */
    public function setIcons($icons)
    {
        $this->icons = $icons;

        return $this;
    }

    /**
     * Get icons
     *
     * @return string
     */
    public function getIcons()
    {
        return $this->icons;
    }

    /**
     * Set options
     *
     * @param string $options
     *
     * @return Renderer
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Set renderer
     *
     * @param string $renderer
     *
     * @return Renderer
     */
    public function setRenderer($renderer)
    {
        $this->renderer = $renderer;

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
     * Set lastSeen
     *
     * @param \DateTime $lastSeen
     *
     * @return Renderer
     */
    public function setLastSeen($lastSeen)
    {
        $this->lastSeen = $lastSeen;

        return $this;
    }

    /**
     * Get lastSeen
     *
     * @return \DateTime
     */
    public function getLastSeen()
    {
        return $this->lastSeen;
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
}
