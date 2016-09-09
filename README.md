# Prevem: Open source preview manager for emails

Prevem is a batch manager for email previews. It provides a RESTful CRUD API
which works with two agents:

 * *Composer* - The mail user-agent (MUA) in which a user prepares a mailing.
   Generally, the Composer submits a `PreviewBatch` and polls to track its progress.
 * *Renderer* - The backend service which prepares a screenshot of how an email would
   appear when read in different MUAs. Generally, a Renderer polls for a pending
   `PreviewTask` record, prepares a screenshot, and then marks it as completed.

All three components (prevem, the composer, and the renderer) have been/are being developed as a part of a project called Email Preview Cluster which is meant to help users (of CiviCRM) to generate previews (screenshots) of their emails to see what they'll look like to receivers on various email clients.

## Installation (Manual)

### 1. Clone prevem repository
```
git clone https://github.com/prevem/prevem.git
cd prevem
```
### 2. Generate the SSH keys for JWT Authentication bundle
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
