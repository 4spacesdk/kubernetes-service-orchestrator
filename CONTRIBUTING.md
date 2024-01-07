# Setup local environment

## First time setup
### Setup local environment variables
* Copy `.env.example` and paste as `.env`. These variables are used by docker-compose.
* Copy `ci4/env` and paste as `ci4/.env`. These variables are used by CodeIgniter.
### Look for script in the root `package.json` file
### Build Docker and run it
* `Setup: docker-composer up`, this will take a couple of minutes
### Install backend & frontend dependencies
* `Setup: install composer dependencies`
* `Setup: install npm dependencies`
### Setup backend database
* `Setup: migrate`
### Start and login
* Serve frontend: `Run: serve vue (:8951)`
* Go to url: `http://localhost:8951/app`
* Login with credentials admin@4spaces.dk / admin
* Create your own credentials and delete the admin user

## Access local database
* Ensure docker containers are running
* Use your favorite mysql client to connect to the database with the following credentials
    * Host: localhost
    * Username: root
    * Password: root
    * Database: deploy
    * Port: 3390
