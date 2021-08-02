# Prevem: Open source preview manager for emails

Prevem is a batch manager for email previews. It provides a RESTful CRUD API
which works with two agents:

 * *Composer* - The mail user-agent (MUA) in which a user prepares a mailing.
   Generally, the Composer submits a `PreviewBatch` and polls to track its progress.
 * *Renderer* - The backend service which prepares a screenshot of how an email would
   appear when read in different MUAs. Generally, a Renderer polls for a pending
   `PreviewTask` record, prepares a screenshot, and then marks it as completed.
 * *Prevem* - A batched job manager which relays data between composers and renderers.

All three components (prevem, the composer, and the renderer) have been/are being developed as a part of a project called Email Preview Cluster which is meant to help users (of CiviCRM) to generate previews (screenshots) of their emails to see what they'll look like to receivers on various email clients.

## Installation (Manual)

### 1. Clone *prevem* repository
```
git clone https://github.com/prevem/prevem.git
cd prevem
```
### 2. Generate cryptographic keys for JWT Authentication bundle
``` bash
$ mkdir -p app/var/jwt
$ openssl genrsa -out app/var/jwt/private.pem -aes256 4096
$ openssl rsa -pubout -in app/var/jwt/private.pem -out app/var/jwt/public.pem
```
### 3. Provide essential parameters and create schema
```
cp app/config/parameters.yml.dist app/config/parameters.yml
vi app/config/parameters.yml
composer install
./app/console doctrine:schema:create
```


## Getting Started

### 1. Setup the Preview Manager (prevem)
``` bash
$ cd prevem
$ app/console server:start
[OK] Web server listening on http://127.0.0.1:8000
```

### 2. Create a *prevem* user account
Whenever you wish to register a new user account (for a composer or a renderer), you'll need to add a record to the database. When setting up a new installation, you might typically need to create two users:
``` bash
## Create a composer
$ app/console user:create alice --pass=s3cr3t --role=compose

## Create a renderer
$ app/console user:create thunderlook --pass=s3cr3t --role=renderer

## Grant multiple roles
$ app/console user:create omniscient --pass=s3cr3t --role=renderer,compose
```
Also if you need to update a user say ```alice``` then you only need to pass the username and its attribute that need to be update like -
``` bash
## Revoke roles
$ app/console user:create alice --role=''

## Change password
$ app/console user:create alice --pass=n3ws3cr3t
```
( NOTE: if you want to make a user superadmin then use ```--role=admin```instead. )

### 3. Launch a Renderer
The first CLI tool, ```renderer:poll```, makes it easier to write a new renderer. A renderer must poll an HTTP resource, then perform some custom rendering logic, and then post another HTTP resource. This tool lets you write a quick-and-dirty renderer without boilerplate code for HTTP. To use it, create a dummy [rendering script](https://github.com/prevem/prevem/tree/master/src/Prevem/CoreBundle/Tests/sample/render-script.php) say ```examples/dummy-render.php```. Then execute the CLI as
``` bash
$ app/console renderer:poll --url 'http://alice:s3cr3t@localhost:8000/' --name=dummy-renderer --cmd=examples/dummy-render.php
```
( NOTE: user must have ```renderer``` role to run this command )

### 4. Create Batch(es)
The second CLI tool ```batch:create```, is a simple email composer. It submits a PreviewBatch, waits for the response, and downloads the images. This is useful for manually testing a renderer.
``` bash
$ app/console batch:create --subject 'Hello world' --text "Hello world" --render thunderlook,iphone --url 'http://aliceuser:s3cr3tpass@localhost:8000/' --out '/tmp/rendered/'
```
( NOTE: Here it dumps the file at ```/tmp/rendered``` )

### 5. Batch Cleanup
Over time, we may accumulate a lot of previews, and we may want to delete old ones. For example, to delete anything older than two weeks:
``` bash
$ app/console: batch:prune '14 days ago'
```
This evaluates ```strotime('14 days ago')``` and finds any PreviewBatch / PreviewTask which is older -- then deletes any DB records or files produced by it.


## Run Tests
Start running tests of *prevem*. These are the steps:

### 1. Install PHPUnit if not installed.

### 2. Start web server
``` bash
$ cd prevem
$ app/console server:start
[OK] Web server listening on http://127.0.0.1:8000
```

### 3. Run the tests for Controllers which you will find [here](https://github.com/prevem/prevem/tree/master/src/Prevem/CoreBundle/Tests/Controller)
``` bash
## Run unit tests of DefaultController
$ phpunit  -c app/  src/Prevem/CoreBundle/Tests/Controller/RendererControllerTest.php
PHPUnit 4.8.21 by Sebastian Bergmann and contributors.
...

Time: 8.76 seconds, Memory: 29.75Mb
OK (3 tests, 17 assertions)
```

### 4. Run the tests for CLI tools which you will find [here](https://github.com/prevem/prevem/tree/master/src/Prevem/CoreBundle/Tests/Command)
``` bash
## Run unit tests of renderer:poll
$ phpunit  -c app/  src/Prevem/CoreBundle/Tests/Command/RendererPollCommandTest.php
PHPUnit 4.8.21 by Sebastian Bergmann and contributors.
.

Time: 8.75 seconds, Memory: 29.00Mb
OK (1 test, 14 assertions)
```
( NOTE: Aslo you can run all the tests at once by ```phpunit -c app/``` )
