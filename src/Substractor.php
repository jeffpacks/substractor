<?php

namespace jeffpacks\substractor;

use Closure;

/**
 * Provides methods for extracting substrings from strings using the Substractor pattern format.
 *
 * A Substractor pattern is a string that may contain the wildcards * and ? in addition to macros {likeThis}.
 * Macros are used for extracting named sub-strings. The wildcards are used for matching arbitrary sub-strings.
 *
 * Substractor format by examples:
 * "path/to/*.json" matches "path/to/composer.json", "path/to/package.json" but also "path/to/some/other/file.json"
 * "1.0.?" matches "1.0.0" and "1.0.1" but not "1.0.10"
 * "1.0.??" matches "1.0.10" and "1.0.15" but not "1.0.5"
 * "{major}.{minor}.{patch}" extracts "2", "5" and "1" respectively from "2.5.1"
 */
class Substractor {

	private static array $preRedactions = [];
	private static array $postRedactions = [];

	/**
	 * Extracts and provides the first macros found in a given string.
	 *
	 * @param string $string The string to extract the macros from.
	 * @param string|string[] $macroPattern A Substractor pattern with macros to extract or an array of such, optionally keyed by a key pattern.
	 * @param string|string[]|null $redact Characters to redact before extraction.
	 * @return string[] Zero or more macro-name => substring entries, each entry representing a macro
	 */
	public static function macros(string $string, $macroPattern, $redact = null): array {
		return self::runMacroSearch($string, $macroPattern, $redact, function(string $regExPattern, string $string) {
			preg_match("/$regExPattern/", $string, $macroValueResult);
			return $macroValueResult;
		});
	}

	/**
	 * Extracts and provides any macros found in a given string.
	 *
	 * @param string $string The string to extract the macros from.
	 * @param string|string[] $macroPattern A Substractor pattern with macros to extract or an array of such, optionally keyed by a key pattern.
	 * @param string|string[]|null $redact Characters to redact before extraction.
	 * @return string[] Zero or more macro name => substring entries, each string representing a macro
	 */
	public static function macrosAll(string $string, $macroPattern, $redact = null): array {
		return self::runMacroSearch($string, $macroPattern, $redact, function(string $regExPattern, string $string) {
			preg_match_all("/$regExPattern/", $string, $macroValueResult);
			return $macroValueResult;
		});
	}

	/**
	 * Extracts and provides any macros found in a given string, based on a given extraction function.
	 *
	 * @param string $string The string to extract the macros from.
	 * @param string|string[] $macroPattern A Substractor pattern with macros to extract or an array of such, optionally keyed by a key pattern.
	 * @param string|string[]|null $redact Characters to redact before extraction.
	 * @param Closure $searchFunction The function that performs the extraction (should return preg_match() or preg_match_all() results)
	 * @return string[]|array[] Zero or more macro name => substring entries, each entry representing one or more macro matches
	 */
	private static function runMacroSearch(string $string, $macroPattern, $redact, Closure $searchFunction): array {

		$macroPatterns = (array) $macroPattern;

		$macroMaps = [];

		$string = self::preRedact($string, $redact);

		foreach ($macroPatterns as $index => $macroPattern) {
			# If the index is a string, it should represent a Substractor pattern that the string must match before any extraction is carried out
			if (!is_integer($index) && is_string($index) && !self::matches($string, $index)) {
				continue;
			}

			# Get a reg-ex pattern we will use to extract all macro tokens (protecting any '{' and '}' from escaping)
			$macroRegExPattern = self::substractorToRegEx($macroPattern, ['{', '}']);
			preg_match_all('/{(\S*?)}/', $macroRegExPattern, $macroTokens);

			# Prepare macro token => reg-ex translations
			$macroTranslations = [];
			if (isset($macroTokens[1])) { # Entry [1] contains macro names (macro tokens without the {}'s)
				foreach ($macroTokens[1] as $macroName) {
					$macroTranslations = array_merge($macroTranslations, ['{' . $macroName . '}' => '(\S*?)']);
				}
			}

			# Get a reg-ex pattern where each macro token is replaced with a reg-ex token
			$regExPattern = self::substractorToRegEx($macroPattern, $macroTranslations);

			# Get the values each macro token corresponds to
			$macroValueResult = $searchFunction($regExPattern, $string);

			# Get the macro name from each macro token
			preg_match_all('/{(.*?)}/', $macroPattern, $macroNameResult);

			# Now map each macro name to the corresponding macro value
			$macroMap = [];
			if (isset($macroNameResult[1])) {
				$macroNames = $macroNameResult[1];
				foreach ($macroNames as $macroNameIndex => $macroName) {
					if (array_key_exists($macroNameIndex + 1, $macroValueResult)) {
						$macroMap[$macroName] = $macroValueResult[$macroNameIndex + 1];
					}
				}
			}

			$macroMaps[] = $macroMap;
		}

		# In case no macros matched
		if (count($macroMaps) === 0) {
			return [];
		}

		# Return the result with the highest amount of macros
		$record = 0;
		$winner = 0;
		foreach ($macroMaps as $index => $macroMap) {
			if (count($macroMap) > $record) {
				$record = count($macroMap);
				$winner = $index;
			}
		}

		$result = $macroMaps[$winner];

		return self::postRedact($result);

	}

	/**
	 * Indicates whether a string fully matches a given pattern.
	 *
	 * @param string $string The string of words to be matched.
	 * @param string $pattern The wildcard pattern to match against.
	 * @param string|string[]|null $redact Characters to redact (set to space) before matching.
	 * @return boolean True if the pattern matches, false otherwise
	 */
	public static function matches(string $string, string $pattern, $redact = null): bool {

		$regExPattern = self::substractorToRegEx($pattern);

		$string = $redact ? str_replace($redact, '', $string) : $string;

		return (bool) preg_match("/$regExPattern/", $string);

	}

	/**
	 * Extracts and provides a single substring.
	 *
	 * @param string $string The string to extract the macros from.
	 * @param string|string[] $macroPattern A macro pattern with macros to extract or an array of such, optionally keyed by a key pattern.
	 * @param string $macroName The name of the macro whose substring to return.
	 * @param string|string[]|null $redact Characters to redact before extraction.
	 * @return string|null
	 */
	public static function pluck(string $string, string $macroPattern, string $macroName, $redact = null): ?string {
		return self::macros($string, $macroPattern, $redact)[$macroName] ?? null;
	}

	/**
	 * Extracts and provides a set of substrings.
	 *
	 * @param string $string The string to extract the macros from.
	 * @param string|string[] $macroPattern A macro pattern with macros to extract or an array of such, optionally keyed by a key pattern.
	 * @param string $macroName The name of the macro whose set of substrings to return.
	 * @param string|string[]|null $redact Characters to redact before extraction.
	 * @return string[]
	 */
	public static function pluckAll(string $string, string $macroPattern, string $macroName, $redact = null): array {
		return self::macrosAll($string, $macroPattern, $redact)[$macroName] ?? [];
	}

	/**
	 * @param array $strings
	 * @return array
	 */
	private static function postRedact(array $strings): array {

		foreach (array_keys($strings) as $key) {
			if (self::$preRedactions) {
				$strings[$key] = str_replace(array_values(self::$preRedactions), array_keys(self::$preRedactions), $strings[$key]);
			}
			if (self::$postRedactions) {
				$strings[$key] = str_replace(self::$postRedactions, '', $strings[$key]);
			}
		}

		self::$preRedactions = [];
		self::$postRedactions = [];

		return $strings;

	}

	/**
	 * Redacts any occurrence of a given sub-string from a given string.
	 *
	 * @param string $string The string to redact the sub-strings from.
	 * @param $redact string|string[]|bool[] A sub-string or strings to redact, or an array keyed by sub-strings to redact and valued as TRUE for full redaction, FALSE for post-redaction only, NULL for
	 * @return string
	 */
	private static function preRedact(string $string, $redact): string {

		if (!$redact) {
			return $string;
		}

		# Create a redaction map, so we can restore the redaction tokens after the regex ops are done
		self::$preRedactions = [];
		$redact = is_array($redact) ? $redact : [$redact];

		foreach ($redact as $key => $value) {

			if (is_string($key)) {
				if ($value === true) { # Full redaction – remove prior to matching and remove from returned result
					$string = str_replace($key, '', $string);
				} elseif ($value === false) { # Post-redaction – keep prior to matching but remove from returned result
					self::$postRedactions[] = $key;
				} else { # Pre-redaction – remove prior to matching but keep in returned result
					self::$preRedactions[$key] = md5($key);
				}
			} else {
				self::$preRedactions[$value] = md5($value);
			}

		}

		# Perform the pre-redactions
		return self::$preRedactions ? str_replace(array_keys(self::$preRedactions), array_values(self::$preRedactions), $string) : $string;


	}

	/**
	 * Replaces or manipulates sub-strings in a given string.
	 *
	 * The object returned from this method will have methods that correpond to the macros of the given macro pattern.
	 * Each such method can be called with a string parameter to replace the matching macro with that string, or with a
	 * function to manipulate the matching sub-string.
	 *
	 * Example:
	 * echo (string) Substractor::replace(
	 *     'https://john:myPassword@example.test:22',
	 *     '{protocol}://{user}:{pass}@{host}:{port}'
	 * )->port(2222)->pass(fn($pass) => md5($pass))
	 * // Outputs: https://john:deb1536f480475f7d593219aa1afd74c@example.test:2222
	 *
	 * @param string $string The string whose sub-strings to replace or manipulate.
	 * @param string $macroPattern A Substractor pattern with macros that can be further manipulated.
	 * @param string|string[]|null $redact Characters to redact before extraction.
	 * @return object An anonymous class instance with methods corresponding to each macro in the given macro pattern
	 */
	public static function replace(string $string, string $macroPattern, $redact = null): object {

		$macros = self::macros($string, $macroPattern, $redact);

		return new class($string, $macros) {

			private string $string;
			private array $macros;
			private array $replacements = [];

			public function __construct(string $string, array $macros) {
				$this->string = $string;
				$this->macros = $macros;
				foreach ($macros as $token => $subString) {
					$this->string = substr_replace($this->string, $token, strpos($this->string, $subString), strlen($subString));
				}
			}

			/**
			 * Replaces the token that corresponds to the method name with the given parameter string or function.
			 *
			 * @param string $methodName
			 * @param array $parameters
			 * @return mixed
			 */
			public function __call(string $methodName, array $parameters) {

				if (array_key_exists($methodName, $this->macros)) {
					$parameter = reset($parameters);
					$parameter = is_array($parameter) ? reset($parameter) : $parameter;
					if ($parameter instanceof Closure) {
						$this->replacements[$methodName] = $parameter($this->macros[$methodName]);
					} elseif (is_string($parameter) || is_numeric($parameter)) {
						$this->replacements[$methodName] = $parameter;
					}
				}

				return $this;

			}

			public function __toString(): string {

				foreach ($this->macros as $token => $subString) {
					$replacement = $this->replacements[$token] ?? $this->macros[$token];
					$this->string = substr_replace($this->string, $replacement, strpos($this->string, $token), strlen($token));
				}

				return $this->string;

			}

		};

	}

	/**
	 * Provides any sub-strings that fully match a given pattern.
	 *
	 * @param string $string The string to be searched.
	 * @param string|string[] $pattern The Substractor pattern to match against, an array of such or an array where each Substractor pattern is keyed by a key pattern.
	 * @param string|string[]|null $redact Characters to redact from the given string before extraction (default), redact from the result after extraction, or both.
	 * @return string[] The strings matching the given pattern
	 */
	public static function subs(string $string, $pattern, $redact = null): array {

		$substractorPatterns = (array) $pattern;

		$preRedacted = self::preRedact($string, $redact);

		$result = [];

		foreach ($substractorPatterns as $index => $pattern) {
			# If the index is a string, it should represent a Substractor pattern that the string must match before any extraction is carried out
			if (!is_integer($index) && is_string($index) && !self::matches($string, $index)) {
				$subs = Substractor::subs($preRedacted, $index);
				foreach ($subs as $sub) {
					$result[] = Substractor::subs($sub, $pattern);
				}
			} else {
				$regExPattern = self::substractorToRegEx($pattern);

				preg_match_all("/$regExPattern/", $preRedacted, $matches);

				if (is_array($matches)) {
					$result = array_merge($result, $matches[0]);
				}
			}

		}

		return self::postRedact($result);

	}

	/**
	 * Converts a Substractor pattern and provides it as a regular expression pattern.
	 *
	 * @param string $substractorPattern A Substractor pattern.
	 * @param string[] $additionalTranslations Zero or more from => to translation entries, or non-keyed entries of characters not to be escaped.
	 * @return string A regular expression pattern
	 */
	private static function substractorToRegEx(string $substractorPattern, array $additionalTranslations = []): string {

		$defaultTranslations = [
			'?' => '.{1}', # Must come before the * translation below or its ? quantifier gets replaced with this one
			'*' => '\S*?', # The ? forces a lazy matching, so "x1x" and "x2x" are found separately in "x1x x2x"
			'/' => '\/' # An escape that preq_quote() doesn't do but that otherwise flips out preg_match
		];

		# Make sure all translations have a string key and a value
		$normalizedTranslations = [];
		foreach ($additionalTranslations as $from => $to) {
			if (is_integer($from)) {
				$normalizedTranslations[$to] = $to;
			} else {
				$normalizedTranslations[$from] = $to;
			}
		}

		$translations = array_merge($defaultTranslations, $normalizedTranslations);

		# Convert the from-characters to tokens the preg_quote() function won't escape
		$hide = [];
		foreach (array_keys($translations) as $from) {
			$hide[$from] = md5($from);
		}

		# Hide the from-characters from the upcoming preg_quote() function
		$regExPattern = str_replace(array_keys($hide), array_values($hide), $substractorPattern);

		# Escape reg-ex control characters
		$regExPattern = preg_quote($regExPattern);

		# Bring the from-characters back
		$regExPattern = str_replace(array_values($hide), array_keys($hide), $regExPattern);

		# Translate the from-characters into the to-characters
		$regExPattern = str_replace(array_keys($translations), array_values($translations), $regExPattern);

		# The last token should be greedy (remove the "?" modifier)
		if (strrpos($regExPattern, '\S*?') === strlen($regExPattern) - 4) {
			$regExPattern = substr_replace($regExPattern, '', strlen($regExPattern) - 1, 1);
		} elseif (strrpos($regExPattern, '(\S*?)') === strlen($regExPattern) - 6) {
			$regExPattern = substr_replace($regExPattern, '', strlen($regExPattern) - 2, 1);
		}

		return $regExPattern;

	}

}
