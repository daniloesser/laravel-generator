# Laravel Generator


The Laravel Generator is a collection of Laravel CLI commands which aim is 
to help the development process of Laravel applications by 
providing some convenient code-generation capabilities.


This package was cloned from https://github.com/reliese/laravel and it was updated to support more features.

## How does it work?

This package expects that you are using Laravel 5.1 or above.
You will need to import the `daniloesser/laravel-generator` package via composer:

```shell
composer require daniloesser/laravel-generator
```

### Configuration

Add the service provider to your `config/app.php` file within the `providers` key:

```php
// ...
'providers' => [
    /*
     * Package Service Providers...
     */

    CliGenerator\Code\CodeServiceProvider::class,
],
// ...
```
### Configuration for local environment only

If you wish to enable generators only for your local environment, you should install it via composer using the --dev option like this:

```shell
composer require daniloesser/laravel-generator --dev
```

Then you'll need to register the provider in `app/Providers/AppServiceProvider.php` file.

```php
public function register()
{
    if ($this->app->environment() == 'local') {
        $this->app->register(\CliGenerator\Code\CodeServiceProvider::class);
    }
}
```

## Models



Add the `cli-generator.php` configuration file to your `config` directory and clear the config cache:

```shell
php artisan vendor:publish --tag=cli-generator
php artisan config:clear
```

### Usage

- You can scaffold a specific table like this:

```shell
php artisan code:generate
```

- You can also specify the connection:

```shell
php artisan code:generate --connection=mysql
```

- If you are using a MySQL database, you can specify which schema you want to scaffold:

```shell
php artisan code:generate --schema=shop
```

#### Support

For the time being, this package only supports MySQL databases.



