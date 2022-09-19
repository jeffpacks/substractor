# About
Substractor is a utility for matching strings against wildcard patterns and for extracting sub-strings according to macro patterns. The pattern format is extremely simple and easy to understand, suitable for simple string matching and extraction tasks without the need for those wonderful and pesky regular expressions. 

# Dependencies
This library requires at least PHP 7.4.

# Installing

## Composer
Run `composer require jeffpacks/substractor` in your project's root directory.

## Downloading
`git clone https://github.com/jeffpacks/substractor.git` or download from `https://github.com/jeffpacks/substractor/archive/refs/heads/master.zip`.

Add `require('/path/to/substractor/autoload.php')` to your PHP script, and you're good to go. 

# The patterns
Substractor uses only two types of wildcard tokens:
- `*` matches any sub-string of zero or more characters
- `?` matches any single character

You can then build patterns like these and match them against strings:
- `http*://example.test` matches `http://example.test` and `https://example.test`
- `https://example.???` matches `https://example.com` but not `https://example.io`

The wildcard tokens are used for matching, but can also be used for extracting sub-strings.

There is a third type of tokens, called macros, that you can use to extract named parts of a string. A macro called `foo` looks like this: `{foo}`. Extracting the protocol, domain and top-leve domain of a URL would require a pattern like this: `{protocol}://{domain}.{top}*`

And that's it. There is a ton of pattern restrictions you won't be able to use Substractor for, but that's why we have `preg_match()` and pals. Substractor is still very useful for simple sub-string matching and extraction.

# The methods

## Substractor::matches()
This method indicates whether a given string matches a pattern that you specify. Here' an example:

```php
<?php
use jeffpacks\substractor\Substractor;

# Checks if a string contains a URL with a subdomain
Substractor::matches(
    'https://example.test/welcome.html https://example.test https://sub.example.test/index.html',
    'https://*.*.*/' # Trailing slash is important, otherwise the dot in welcome.html would count
));
```

## Substractor::subs()
We can extract all sub-strings that fully match a given pattern with this method. Say you want to extract all e-mail addresses from a string. It'll be easy – like breaking a toothpick:
```php
$addresses = Substractor::subs('Please contact jeffpacks@varen.no or jfvaren@gmail.com for more info', '*@*.*');
```

## Substractor::macros()
This one is powerful. It allows you to extract named sub-strings. If you want to parse a route URL, like in Laravel, you would do it like so:
```php
# Returns ['userId' => '42', 'settingId' => '3221']
$segments = Substractor::macros('/user/42/settings/3221', '/user/{userId}/settings/{settingId}');
```

You can of course specify multiple patterns:
```php
# Returns ['userId' => '42', 'settingId' => '3221']
$segments = Substractor::macros(
    '/user/42/settings/3221',
    [
        '/user/{userId}/settings/{settingId}'
        '/users/{userId}/settings/{settingId}'
    ]
);
```

There may be cases where you want to use one macro pattern over another based on the general pattern of the string you're searching. Here's an example:
```php
$patterns = [
    '*.*.*-alpha.*' => '{major}.{minor}.{patch}-*.{alpha}',
    '*.*.*-beta.*' => '{major}.{minor}.{patch}-*.{beta}'
];

# Returns ['major' => '1', 'minor' => '2', 'patch' => '3', 'alpha' => '1']
$segments = Substractor::macros('1.2.3-alpha.1', $patterns);

# Returns ['major' => '1', 'minor' => '2', 'patch' => '3', 'beta' => '1']
$segments = Substractor::macros('1.2.3-beta.1', $patterns);
```

## Substractor::replace()
If you want to replace or manipulate a specific sub-string within some string, you can use the `Substractor::replace()` method. By specifying a macro pattern, you can tell it which of those macros you want to replace or manipulate. Here's an example where we encrypt and decrypt the password segment of a URL:

```php
$secret = 'OQvBIbYzkz';
$url = 'https://john:password123@example.test';

$encryptedUrl = (string) Substractor::replace($url, '*://*:{pass}@*')->pass(fn($password) => openssl_encrypt($password, 'AES-128-CTR', $secret, 0, '1234567891011121'));

$decryptedUrl = (string) Substractor::replace($encryptedUrl, '*://*:{pass}@*')->pass(fn($password) => openssl_decrypt($password, 'AES-128-CTR', $secret, 0, '1234567891011121'));

echo "Encrypted: $encryptedUrl\n";
echo "Decrypted: $decryptedUrl\n";

# Outputs:
# Encrypted: https://john:emIdK6ITultd0S0=@example.test
# Decrypted: https://john:password123@example.test
```

The object returned from `Substrator::replace()` have (magic) methods that correspond to the macros you specify in your macro pattern. This way, you can specifically target the sub-strings you want to replace, even if some of them are identical. Each method will return the same object, so you can chain your methods:

```php
$url = 'http://john:https@example.test:80';

echo (string) Substractor::replace($url, '{protocol}://{user}:{pass}@{host}:{port}')->protocol('https')->port('22');

# Outputs:
# https://john:password123@example.test:22
```

## Redaction
Sometimes characters get in the way of what you're trying to match and/or extract. Consider a Markdown document containing links, such as this one: `You can [e-mail me](mailto:jeffpacks@varen.no) or reach me on [Github](https://github.com/jeffpacks)`. Attempting to extract the sub-string `e-mail me` using the pattern `[*]` would yield no result because the single `*` wildcard tries to match a single word, but there are two words between these brackets. This is a case where we can use the `redaction` parameter that all the Substractor methods accept. There are 3 types of redactions:
1. Pre-redaction: A given character/string will be removed before the matching takes place, but is left untouched in the returned sub-strings
2. Post-redaction: A given character/string is left untouched prior to matching, but is removed from the returned sub-strings
3. Full redaction: A given character/string is removed prior to matching and is also removed from the returned sub-strings (a combination of pre- and post-redaction).

We can use pre-redaction to remove the space character between the two words prior to matching, but allow it to remain untouched in the returned sub-string:
```php
$markdown = 'You can [e-mail me](mailto:jeffpacks@varen.no) or reach me on [Github](https://github.com/jeffpacks)';
$result = Substractor::subs($markdown, '[*]', ' ');
```
The above will give the sub-string `[e-mail me]`, including the brackets. If we don't want the brackets, we will have to do a post-redaction on those:
```php
$markdown = 'You can [e-mail me](mailto:jeffpacks@varen.no) or reach me on [Github](https://github.com/jeffpacks)';
$result = Substractor::subs($markdown, '[*]', [
    ' ', # pre-redact the space
    '[' => false, # false indicates post-redaction 
    ']' => false
]);
```
This gives us the `e-mail me` sub-string only, as the brackets have been post-redacted.

Full redaction is a less common use-case, but is indicated by specifying a boolean `true` as the value of the redaction array.

# Authors
* [Johan Fredrik Varen](mailto:jeffpacks@varen.no)

# License
MIT License
