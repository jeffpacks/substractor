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

## The methods

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

## Redaction
Attempting to extract the `mailto` URI from the Markdown string `[e-mail](mailto:jeffpacks@varen.no)` using the pattern `mailto:*@*` would result in the string `mailto:jeffpacks@varen.no)` (note the trailing parenthesis). All `Substractor` methods accept a `$redact` parameter that lets you redact/remove any given characters before the matching or extraction is performed.
Using redaction, we can get rid of the trailing parenthesis like this:
```php
$result = Substractor::subs('[e-mail](mailto:jeffpacks@varen.no)', 'mailto:*@*', ')');
```

Another common need for redactions is when your wildcard or macro should also match whitespaces. Consider the string `[Click me](https://example.test)` where the link text contains a space character. This string would not match with a pattern like `[*](*)` because the `*` wildcard tries to match whole words and does not include whitespaces. This can be solved by specifying the space character as something to redact before the matching or extraction is performed. Here's an example:
```php
$string = '[Click me](https://example.test)';
$isMarkdownLink = Substractor::matches($string, '[*][http?://*]', ' ');
$segments = Substractor::macros($string, '[{text}]({url})', ' ');
# $segments is now ['text' => 'Click me', 'url' => 'https://example.test']
```

Substrings that are redacted prior to extraction are restored back into the returned returned result. In the example above, the space in `Click me` is removed before the extraction, but is restored back in so the `text` entry is still `Click me`. 

# Authors
* [Johan Fredrik Varen](mailto:jeffpacks@varen.no)

# License
MIT License