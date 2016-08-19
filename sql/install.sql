//This sql file is used for generating entities
//ref: http://symfony.com/doc/2.7/doctrine/reverse_engineering.html



CREATE TABLE `prevem_db`.`preview_task`
  ( `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'A unique ID for this task.',
    `user` VARCHAR(63) NOT NULL COMMENT 'The user who owns this task.',
    `batch` VARCHAR(63) NOT NULL COMMENT 'The batch which produced this task. (FK: PreviewBatch.name)',
    `renderer` VARCHAR(63) NOT NULL COMMENT 'The renderer for this batch.',
    `options` TEXT NOT NULL COMMENT 'A JSON-encoded set of options to pass through to the renderer.',
    `create_time` TIMESTAMP NOT NULL COMMENT 'The time at which the PreviewTask was initially created.',
    `claim_time` TIMESTAMP NOT NULL COMMENT 'The time at which the PreviewTask was last claimed.',
    `finish_time` TIMESTAMP NOT NULL COMMENT 'The time at which the PreviewTask completed.',
    `attempts` INT NOT NULL COMMENT 'The number of times we have attempted to render this task.',
    `error_message` TEXT NOT NULL,
     PRIMARY KEY (`id`)
   ) ENGINE = InnoDB;


CREATE TABLE `prevem_db`.`preview_batch`
  ( `user` VARCHAR(63) NOT NULL COMMENT 'The user who owns this batch.',
    `batch` VARCHAR(63) NOT NULL COMMENT 'A unique name for this batch.',
    `message` TEXT NOT NULL COMMENT 'A JSON-encoded object.',
    `create_time` TIMESTAMP NOT NULL COMMENT 'The time at which the PreviewBatch was initially created.',
    PRIMARY KEY (`user`, `batch`)
  ) ENGINE = InnoDB;

CREATE TABLE `prevem_db`.`renderer`
  ( `renderer` VARCHAR(63) NOT NULL COMMENT 'A unique symbolic name. (ex: winxp-thunderlook-9.1)',
    `title` VARCHAR(255) NOT NULL COMMENT 'A displayable title. (ex: Thunderlook 9.1 (Windows XP))',
    `os` VARCHAR(63) NOT NULL COMMENT ' A symbolic name of a platform. (ex: linux, windows, darwin)',
    `os_version` VARCHAR(63) NOT NULL COMMENT 'The version of the platform.',
    `app` VARCHAR(63) NOT NULL COMMENT 'A symbolic name of the email application (ex: thunderlook, gmail)',
    `app_version` VARCHAR(63) NOT NULL COMMENT 'The version of the email application.',
    `icons` TEXT NOT NULL COMMENT 'A JSON-encoded object.',
    `options` TEXT NOT NULL COMMENT 'A JSON-encoded list of options supported by this renderer.',
    `last_seen` TIMESTAMP NOT NULL COMMENT 'The last time we last had communication with this renderer.',
    PRIMARY KEY (`renderer`)
  ) ENGINE = InnoDB;
