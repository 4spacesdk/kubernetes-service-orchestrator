# Kubernetes Service Orchestrator
The Kubernetes Service Orchestrator (KSO) is a powerful service deployment solution developed using PHP and Vue. 
This service enables interaction with the Kubernetes API and facilitates the effortless deployment of other services within a Kubernetes cluster.

## Features
* Automated deployment of services in Kubernetes clusters.
* User-friendly Vue-based interface for easy configuration and monitoring.
* Handling of Kubernetes API calls and resource management.
* Option for custom configurations and adaptations.

## How to Use
* This repository works as a base project for creating your own KSO. 
  * Check this guide for private fork https://gist.github.com/0xjac/85097472043b697ab57ba1b1c7530274
* Feel free to fork this repository, set it up locally and start playing around.

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
