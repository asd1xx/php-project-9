### Hexlet tests and linter status:
[![Actions Status](https://github.com/asd1xx/php-project-9/actions/workflows/hexlet-check.yml/badge.svg)](https://github.com/asd1xx/php-project-9/actions)
[![asd1xx-check](https://github.com/asd1xx/php-project-9/actions/workflows/asd1xx-check.yml/badge.svg)](https://github.com/asd1xx/php-project-9/actions/workflows/asd1xx-check.yml)
[![Maintainability](https://api.codeclimate.com/v1/badges/480af8b4125aa32cb8b1/maintainability)](https://codeclimate.com/github/asd1xx/php-project-9/maintainability)

# Page analyzer

## About the project

This is a site that analyzes the specified pages for SEO suitability, similar to PageSpeed Insights.  
The page Analyzer is a full-fledged application based on the Slim framework. Here, the basic principles of building modern websites on the MVC architecture are worked out: working with routing, query handlers and a template engine, interacting with a database.

## System requirements

- Composer 2.6.6
- PHP 8.3
- Slim 4.14
- PostgreSQL 12.20

## Installation instructions

Perform the following steps in sequence:

1. Clone the repository:
    
    ```bash
    git@github.com:asd1xx/php-project-9.git page-analyzer
    ```
    
2. Go to the project directory:
    
    ```bash
    cd page-analyzer
    ```
    
3. Installing dependencies:
    
    ```bash
    composer install
    ```
    
4. Start a project:
    
    ```bash
    make start
    ```

5. Open in browser:
    
    ```bash
    http://localhost:8000
    ```

## Information about routes and requests

- GET   /                   - main page
- GET   /urls               - getting a list of all verified sites
- GET   /urls/{id}          - viewing information about the site
- POST  /urls               - create a verification of the entered site
- POST  /urls/{id}/checks   - run a site check