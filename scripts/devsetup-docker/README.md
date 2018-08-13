# docker-compose devsetup

[![Build Status](https://travis-ci.org/drunomics/devsetup-docker.svg?branch=1.x)](https://travis-ci.org/drunomics/devsetup-docker)

A simple devsetup based upon docker-compose. The devsetup makes use of the
drunomics docker images by default.

## Usage

The variables in the .env file are defaults and should be overridden by
environment variables that are sourced in the shell; e.g.:

    source dotenv/loader.sh
    docker-composer up -d

The defaults provided in .env should be added to the project's .env files.
In addition a COMPOSE_FILE variable needs to be declared, so that docker
compose finds the compose files:

	COMPOSE_FILE=devsetup-docker/docker-compose.yml:devsetup-docker/service-chrome.yml

Then, once the environment is loaded, it can be simply used:
	
	docker-compose up -d
    
Alternatively, this can be handled by a wrapper script, of course.

## Configuration

Refer to the commented environment variables in the `.env` file.

## Credits

(c) 2018 drunomics GmbH. /  MIT License
