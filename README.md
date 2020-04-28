# Obtainable

#### Status

v0.2 (2020-28-04) - in-development

<br />

### About this package

**This package is "work in progress". Nothing in this repository is production ready!**

<br />

### Installation

Install with composer with `composer require wize-wiz/laravel-obtainable`.

This package supports auto-discovery, after installation run `php artisan package:discover` to install the configuration file.

<br />

### Config

The configuration file has a `namespace` setting, where all obtainable classes are located. The default namespace for all the Eloquent models in Laravel is `App`. If your project deviates from Laravels default, change the `models` namespace accordingly.

```php
    /*
    |--------------------------------------------------------------------------
    | Obtainable namespace
    |--------------------------------------------------------------------------
    |
    | This option controls the default namespace for any obtainable class. The
    | default namespace `App\Obtainables` equals the directory `app\Obtainables`.
    |
    */
    
    'namespace' => 'App\\Obtainables'
    
    
    /*
    |--------------------------------------------------------------------------
    | Model namespace
    |--------------------------------------------------------------------------
    |
    | By default, Laravels models uses the `App` namespace for all models. If
    | your project deviates from this setup, change the models namespace 
    | accordingly.
    |
    */
    
    'models' => 'App'
    
```

Each obtainable class will reflect the namespace of its model. So for example, if the `User` model would be located under `App\Models`, change the `'models'` setting to `App\Models`. The obtainable class for `User` would then be located under `App\Obtainables\User`.

If a model is devided into multiple sub-spaces, e.g. `App\Models\Chat\Message`, then the obtainable class for `Message` would be located under `App\Obtainables\Chat\Message`. This is done automatically by the Obtainer class.

<br />

### Usage

Implement the obtainable concern and contract, in this example, the `User` model.

```php
namespace App\Models;

use use Illuminate\Database\Eloquent\Model;
use WizeWiz\Obtainable\Contracts\Obtainable;
use WizeWiz\Obtainable\Concerns\IsObtainable;

class User extends Model implements Obtainable {
    use IsObtainable;

    ...

}
```

Create an obtainable class for the model `User`. Obtainable class name should be the class name of the given model. Each method declared in the obtainable class should return a closure. Each closure has a parameter called `options` where any relevant arguments can be passed to be used in the closure. By default, the models `id` is automatically added.

```php
namespace App\Obtainables;

use WizeWiz\Obtainable\Obtainer;

// Obtainer class for model User.
class User extends Obtainer {

    public function allUsers() {
        return function() {
            return User::all();
        }
    }

	/**
	 * Return all unread messages.
	 */
    public function unreadMessages() {
        return function($ids) {
            return User::find($ids['id'])->unread_messages;
        }
    }

	/**
	 * Return the cound of all unread messages.
	 */
    public function unreadMessagesCount() {
        return function($ids) {
            return User::find($ids['id'])->unread_messages->count();
        }
    }

}
```

To retrieve the results from any obtainable method, you call the methods in kebab style. 

```php
// (statically called) get all users
Users::obtainable('unread-messages');

// get unread messages count from specific user.
$User = User::find(1);
$User->obtain('unread-Messages-count')
// or get all messages for this user.
$User->obtain('unread-messages').
```

If the closure requires additional data, just append any data as an `array` to the `obtain` method:

```php
// get all unread chat messages for chat id 582.
$User->obtain('unread-chat-messages', ['chat' => 582]).
```

Where the obtainable method for `unread-chat-messages` could look like:

```php
// Obtainer class for model User.
class User extends Obtainer {

    public function unreadChatMessages() {
        return function($ids) {
            // psuedo chat class
            return Chat::with('messages')
                    ->whereIsNull('messages.unread_at')
                    ->where('messages.user_id', $ids['id'])
                    ->where('messages.chat_id', $ids['chat']);
        }
    }

}
```

<br />

### Obtainable options

```php
// Obtainer class for model User.
class User extends Obtainer {

    // prefix the cache key, all keys will start with `chat:` followed by the key.
    public $prefix = 'chat';

    // given default TTL when none supplied in the `$ttl_map`
    public $ttl = 1800;

    // if the obtainable is allowed to fail by throwing an exception.
    public $silent = true;

}
```

<br />

### Obtainable mapping

Control the format of the cache keys being generated by the obtainable class.

```php
class User extends Obtainer {

    public $prefix = 'chat';

    public $key_map = [
        'unread-chat-messages' => '$id:unread-messages:$user'
    ]

    // prefix the cache key, all keys will be start with `chat:` followed by the key.
    public $ttl_map = [
        'unread-chat-messages' => 120 // time to live of 120 seconds.
    ];

}
```

If key mapping is not used for an obtainable key, in this example `unread-chat-messages`, the `Obtainer` class will
generate a cache key according to the supplied arguments `$ids`, for example:

`chat:unread-chat-messages:chat:12:chat:582`.

In order to control the cached key when `unread-chat-messages` is called, a key like `unread-chat-messages` can be mapped to a different format like `$id:unread-messages:$chat`. Each string sigment (`$id` and `$user`) will be replaced with the corresponding data supplied.

```php
class User extends Obtainer {

    public $prefix = 'user';

    public $key_map = [
        'unread-chat-messages' => '$id:unread-messages:$chat'
    ]

	public function unreadChatMessages() {
		// ...	
	}
	
	public function unreadChatMessagesCount() {
		// ...
	}

}

$User = User::find(1);

// custom cache-key: user:1:unread-messages:582
$User->obtain('unread-chat-messages', ['chat' => 582]);

// default cache-key: user:1:unread-chat-messages-count:chat:582
$User->obtain('unread-chat-messages-count', ['chat' => 582]);


```

<br />

### Tags

All cache entries are being tagged automatically. There are 3 default tags added by each cache entry, a global tag, an obtainer (model) specific tag and a key tag. The following obtainer class would produce the following 3 tags per key entry:

```php
class User extends Obtainer {

   public $prefix = 'user';

	public function unreadChatMessages() {
		// ...	
	}
	
}

User::find(1)->obtain('unread-chat-messages')
```

```php
[
	// default global tag
	'ww:obt' 
	// default obtainer tag
	'obt:user'
	// default key tag
	'obt:user:unread-chat-messages'
]
```

</br>

### Flushing data (cache).

There are various ways to remove stored cache entries.

Removing a specific key entry.

```php
$User->obtain('unread-chat-messages', ['chat' => 582]);
// will remove only the above entry for chat with id 582. 
$User->flushObtainable('unread-chat-messages', ['chat' => 582]);
```

To remove all related entries for a specific key

```php
$User->obtain('unread-chat-messages', ['chat' => 582]);
$User->obtain('unread-chat-messages', ['chat' => 1024]);
// will remove all the above entries for `unread-chat-messages`. 
$User->flushObtainable('unread-chat-messages');
```

To flush all key entries linked to an obtainable.

```php
// obtainable for model User
$User->obtain('unread-chat-messages', ['chat' => 582]);
$User->obtain('unread-chat-messages-count');
// obtainable for model Chat
$Chat->obtain('all-chats');

// will remove only the entries associated with User, not Chat.
$User->flushObtainables();
```

To flush all keys stored used by any obtainable class.

```php
$User->obtain('unread-chat-messages', ['chat' => 582]);
$Chat->obtain('all-chats');
// will remove all the above. 
Obtainer::flush();
```

<br />

### Static calls

Obtainables can also be statically called. The only difference is to add the id of the model manually:

```php
User::obtainable('unread-chat-messages', ['id' => 42, 'chat' => 582]);
```

<br />

### License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

### Todo
- workout readme (0/2)
- add examples (0/2)
- update composer.json for all dependency/requirements. (0/1)
- create a stable version 1.0. (0/1)