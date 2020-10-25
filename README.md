# Laravel Swagger
Simple to use OAS3 compatible documentation generator.  
Also includes Swagger UI.   
[![Total Downloads](https://poser.pugx.org/freedomcore/laravel-swagger/downloads.svg)](https://packagist.org/packages/freedomcore/laravel-swagger)
[![GuitHub Sponsor](https://img.shields.io/static/v1?label=Sponsor%20freedomcore/laravel-swagger&message=%E2%9D%A4&logo=GitHub&link=https://github.com/sponsors/darki73)](https://img.shields.io/static/v1?label=Sponsor%20freedomcore/laravel-swagger&message=%E2%9D%A4&logo=GitHub&link=https://github.com/sponsors/darki73)
## About
This package is heavily inspired by the [mtrajano/laravel-swagger](https://github.com/mtrajano/laravel-swagger) and [DarkaOnLine/L5-Swagger](https://github.com/DarkaOnLine/L5-Swagger).  
Usage is pretty similar to the `mtrajano/laravel-swagger` with the difference being:
1. OAS3 support
2. "Custom decorators" inspired by Nest.JS
3. Automatic generation (assuming relevant configuration option is turned on)
4. Inclusion of Swagger UI

I've spent a couple of hours making this package to suit my needs, and it does so far.  
All I am trying to say is - **I don't think I will be actively maintaining this package unless people will have interest in it**.

## Requirements
This package developed on `Laravel 8.11.2`, but it might work on the previous releases.  
All other requirements are inherited from the `Laravel 8`.
## Installation
#### Install package through composer
```shell
composer require freedomcore/laravel-swagger
```
#### Publish configuration files and views
```shell
php artisan vendor:publish --provider "FreedomCore\Swagger\SwaggerServiceProvider"
```
#### Edit the `swagger.php` configuration file for your liking

## Usage
### @Request() decorator
You can have only one `@Request()` decorator.
```php
/**
* You can also do this, first line will be "summary"
*
* And anything 1 * apart from the "summary" will count as "description"
*
* @Request({
*     summary: Title of the route,
*     description: This is a longer description for the route which will be visible once the panel is expanded,
*     tags: Authentication,Users
* })
*/
public function someMethod(Request $request) {}
```

### @Response() decorator
You can have multiple `@Response` decorators
```php
/**
* @Response({
*     code: 302
*     description: Redirect
* })
* @Response({
*     code: 400
*     description: Bad Request
* })
* @Response({
*     code: 500
*     description: Internal Server Error
* })
*/
public function someMethod(Request $request) {}
```

### Custom Validators
These validators are made purely for visual purposes, however, some of them can actually do validation

#### swagger_default
```php
$rules = [
    'locale'        =>  'swagger_default:en_GB'
];
```
#### swagger_min
```php
$rules = [
    'page'          =>  'swagger_default:1|swagger_min:1', // This will simply display the 'minimum' value in the documentation
    'page'          =>  'swagger_default:1|swagger_min:1:fail' // This will also fail if the `page` parameter will be less than 1
];
```

#### swagger_max
```php
$rules = [
    'take'          =>  'swagger_default:1|swagger_min:1|swagger_max:50', // This will simply display the 'maximum' value in the documentation
    'take'          =>  'swagger_default:1|swagger_min:1|swagger_max:50:fail' // This will also fail if the `take` parameter will be greater than 50
];
```
