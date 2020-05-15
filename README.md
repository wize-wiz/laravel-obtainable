# Obtainable

#### Status

v0.3 (2020-28-04) - in-development

<br />

### About this package

**This package is "work in progress". Nothing in this repository is production ready!**

I needed a simpler solution to manage cached content retrieved by Eloquent models. The relentless use of the `remember` method from the facade `Cache::remember` increased the chance of unnecessary duplication of code using callbacks.

```php
$Chat = Chat::find(528); // some random ids.
$User = User::find(12);
Cache::remember("chat:{$Chat->id}:unread-messages:{$User->id}", 60*60, function() use ($Chat, $User) {
    return $Chat->messages()->whereUserId($User->id)->whereIsNull('unread')->count();
});
```

This package tries to reduce the overhead in a simpler manner without the need to duplicate it anywhere else, or keep track of the cached content and remove it from the cache somewhere completely else.

```php
Chat::find(528)->obtain('unread-messages-count', ['user' => $User->id]);
````

The removal of cached data could be just be as complex if we need to remove the cached data if a new message was sent in the chat.

```php
$Chat = Chat::find(528);
$users = $Chat->users;
foreach($users as $User) {
    Cache::forget("chat:{$Chat->id}:unread-messages-count:{$User->id}");
}
```

Removing with an obtainable class is as simple as:

```php
$Chat = Chat::find(528);
// will remove all count for all users with chat id 528.
$Chat->flushObtained('unread-messages-count');
// will only remove for user with id 12.
$Chat->flushObtained('unread-messages-count', ['user' => 12]);
// will only remove for user id 12, all unread messages count for all chats. 
Chat::flushObtainables('unread-messages-count', ['user' => 12]);
```

It also possible to react on events, for example on a `NewMessage` event where we would like to clear anything cached previously. Read more about it

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

If a model has namespace of, e.g. `App\Models\Chat\Message`, then the obtainable class for `Message` would be located under `App\Obtainables\Chat\Message`. This is done automatically by the Obtainer class.

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
class Chat extends Obtainer {

    public function allUsers() {
        return function(array $options) {
            // function is automatically bound to the Chat model.
            return $this->users->only(['id', 'name']);
        };
    }

	/**
	 * Return all unread messages.
	 */
    public function unreadMessages() {
        return function($options) {
            return $this->unread_messages;
        };
    }

	/**
	 * Return the cound of all unread messages.
	 */
    public function unreadMessagesCount() {
        return function($options) {
            return $this->unread_messages->count();
        };
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
// Obtainer class for model Chat.
class Chat extends Obtainer {

    public function unreadChatMessages() {
        return function($options) {
            // psuedo chat class
            return Chat::with('messages')
                    ->whereIsNull('messages.unread_at')
                    ->where('messages.user_id', $options['id'])
                    ->where('messages.chat_id', $options['chat']);
        }
    }

}
```

<br />

### Obtainable options

```php
// Obtainer class for model User.
class Chat extends Obtainer {

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
class Chat extends Obtainer {

    // prefix the cache key, all keys will be start with `chat:`.
    public $prefix = 'chat';

    public $key_map = [
        // results in: chat:528:unread-messages:1209
        'unread-chat-messages' => '$id:unread-messages:$user'
    ];

    public $ttl_map = [
        'unread-chat-messages' => 120 // time to live of 120 seconds.
    ];

}
```

If key mapping is not used for an obtainable key, in this example `unread-chat-messages`, the `Obtainer` class will
generate a cache key according to the supplied arguments `$options`, for example the call :

`chat:unread-chat-messages:chat:12:user:582`.

In order to control the cached key when `unread-chat-messages` is called, a key like `unread-chat-messages` can be mapped to a different format like `$id:unread-messages:$chat`. Each string sigment (`$id` and `$user`) will be replaced with the corresponding data supplied.

```php
class User extends Obtainer {

    public $prefix = 'user';

    public $key_map = [
        'unread-chat-messages' => '$id:unread-messages:$chat'
    ]

	public function unreadChatMessages() {
		return function($options) {
			// ..
		};
	}
	
	public function unreadChatMessagesCount() {
		return function($options) {
			// ..
		};
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
		return function($options) {
			// ..
		};
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

### Casting.

Deu to the fact that all values stored in a cache are string based, casting can be applied for each obtainable key:

```php
namespace App\Obtainables;

use WizeWiz\Obtainable\Obtainer;

// Obtainer class for model User.
class User extends Obtainer {

	protected $casts = [
		'unread-chat-messages-count' => 'integer'
	];	
	
	public function unreadChatMessagesCount() {
		return function() {
			// psuedo relationship
			return $this
				->chats()
				->whereNull('read')
				->count();
		}
	}
	
}
```

Now `$User->obtain('unread-chat-messages-count')` will always return an integer. Only the types supported with PHP's [settype](https://www.php.net/manual/en/function.settype.php). Additional any timestamp will be converted to a datetime.

</br>

### Flushing data (cache).

There are various ways to remove stored cache entries.

Removing a specific key entry.

```php
$User->obtain('unread-chat-messages', ['chat' => 582]);
// will remove only the above entry for chat with id 582. 
$User->flushObtained('unread-chat-messages', ['chat' => 582]);
```

To remove all related entries for a specific key

```php
$User->obtain('unread-chat-messages', ['chat' => 582]);
$User->obtain('unread-chat-messages', ['chat' => 1024]);
// will remove all the above entries for `unread-chat-messages`. 
$User->flushObtained('unread-chat-messages');
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

### Events

An obtainable class can also respond to an event. For example, a new message in a chat.

```php
class Chat extends Obtainer {
    
    public static $events = [
        NewMessage::class => 'onNewMessage'
    ];

    /**
     * New message in chat event.
     * 
     * @param $name Name of the event.
     * @param $event Event object.
     */
    public function onNewMessage($name, $event) {
        // remove all cached unread-message-count for all users in the chat.
        Chat::find($event->chat_id)->flushObtained('unread-message-count');
        // or statically
        Obtainer::flushObtainable('unread-messages-count', ['id' => $event->chat_id]);
    }
}
```

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