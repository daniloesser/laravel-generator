# Laravel Generator


The Laravel Generator is a Laravel CLI command which aim is 
to help the development process of Laravel applications by 
providing some convenient code-generation capabilities.

At the moment, this package is capable of generating the following:
- [x] **Models** - with eloquent relations
- [x] **Migrations** - with fields and faker types
- [ ] **Seeders** - with fields and faker types
- [ ] **Factories** - with fields and faker types



This package was forked from **reliese/laravel** and it was improved to support more features.
Also, I'm using the package **kitloong/laravel-migrations-generator** to support migration generation. 

## How does it work?

This package expects that you are using Laravel 5.1 or above.
You will need to import the `daniloesser/laravel-generator` package via composer:

```shell
composer require daniloesser/laravel-generator
```

## Configuration

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

### Custom configuration



Add the `cli-generator.php` configuration file to your `config` directory and clear the config cache:

```shell
php artisan vendor:publish --tag=cli-generator
php artisan config:clear
```

## Usage

- Invoke the generator:

```shell
php artisan code:generate
```

- You can also specify the connection:

```shell
php artisan code:generate --connection=mysql
```

- You will be prompted for which tables you want to scaffold from a list. You can pass more than one, separating them by a comma:

```shell
E.G: cities,states,countries
```

- Additionally, you can inform a Model sub-folder to create the files in a specific folder/namespace:

```shell
E.G: Payment
```

- After finishing Models generation, you will be prompted for migration generation as well.


## Support

For now, this package only supports MySQL databases.

Some customization is possible. Just check options inside cli-generator file.



