# 404-email-alert
Laravel Page Not Found (404) Email Alerts

### Install using composer

```
composer require jeylabs/404-email-alert
```

Add below line into config/app.php inside providers array

```php
Jeylabs\PageNotFoundEmailAlert\PageNotFoundEmailAlertServiceProvider::class,
```

### Publish configuration file

```
php artisan vendor:publish
```