<?php

namespace jeffpacks\substractor;

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
 * "{major}.{minor}.{patch}" extracts "2", "5" and "1" respectively
 */
class Substractor {

	/**
	 * Converts a Substractor pattern and provides it as a regular expression pattern.
	 *
	 * @param string $substractorPattern A Substractor pattern.
	 * @param string[] $additionalTranslations Zero or more from => to translation entries, or non-keyed entries of characters not to be escaped.
	 * @return string A regular expression pattern
	 */
	private static function substractorToRegEx(string $substractorPattern, array $additionalTranslations = []): string {

		$defaultTranslations = [
			'?' => '.{1}', // Must come before the * translation below or its ? quantifier gets replaced with this one
			'*' => '.+?', // The ? forces a lazy matching, so "x1x" and "x2x" are found separately in "x1x x2x"
			'/' => '\/' // An escape that preq_quote() doesn't do but that otherwise flips out preg_match
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

		# Translate the the from-characters into the to-characters
		$regExPattern = str_replace(array_keys($translations), array_values($translations), $regExPattern);

		return $regExPattern;

	}

	/**
	 * Extracts and provides any macros found in a given string.
	 *
	 * If the string needs to match a certain pattern before the macros can be extracted, you specify that pattern by
	 * using it as the key of the macro pattern.
	 *
	 * Example:
	 * $patterns = [
	 *     '*.example.com' => '{domain}',
	 *     'example.com' => '{domain}',
	 * ];
	 * Substractor::extractMacros('you.example.com', $patterns); # Returns ['domain' => 'you.example.com']
	 * Substractor::extractMacros('example.com', $patterns); # Returns ['domain' => 'example.com']
	 * Substractor::extractMacros('examplecom', $patterns); # Returns []
	 * Substractor::extractMacros('example.net', $patterns); # Returns []
	 *
	 * @param string $string The string to extract the macros from.
	 * @param string|string[] $macroPattern A Substractor pattern with macros to extract or an array of such.
	 * @return string[] Zero or more name => value entries, each string representing a macro
	 */
	public static function extractMacros(string $string, $macroPattern): array {

		$substractorPatterns = (array) $macroPattern;

		$macroMaps = [];

		foreach ($substractorPatterns as $index => $substractorPattern) {
			# If the index is a string, it should represent a Substractor pattern that the string must match before any extraction is carried out
			if (!is_integer($index) && is_string($index) && !self::matches($string, $index)) {
				continue;
			}

			# Get a reg-ex pattern we will use to extract all macro tokens (protecting any '{' and '}' from escaping)
			$macroRegExPattern = self::substractorToRegEx($substractorPattern, ['{', '}']);

			preg_match_all('/{(.*?)}/', $macroRegExPattern, $macroTokens);

			# Prepare macro token => reg-ex translations
			$macroTranslations = [];
			if (isset($macroTokens[1])) { // Entry [1] contains macro names (macro token without the {}'s)
				foreach ($macroTokens[1] as $macroName) {
					$macroTranslations = array_merge($macroTranslations, [
						' {' . $macroName . '} ' => ' (\S+) ',
						'{' . $macroName . '} ' => '(\S+) ',
						' {' . $macroName . '}' => ' (\S+)',
						'{' . $macroName . '}' => '(.+)',
						' * ' => ' .+ ', // \
						'* ' => '.+ ',   //  } Overriding the default '*' translations so they don't get grouped and mess up the mapping order
						' *' => ' .+',   // /
					]);
				}
			}

			# Get a reg-ex pattern where each macro token is replaced with a reg-ex token
			$regExPattern = self::substractorToRegEx($substractorPattern, $macroTranslations);

			# Get the values each macro token corresponds to
			preg_match("/$regExPattern/", $string, $macroValueResult);

			# Get the macro name from each macro token
			preg_match_all('/{(.*?)}/', $substractorPattern, $macroNameResult);

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

		return $macroMaps[$winner];

	}

	/**
	 * Provides any sub-strings that fully match a given Substractor pattern.
	 *
	 * @param string $string The string to be searched.
	 * @param string|string[] $pattern The Substractor pattern to match against or an array of such.
	 * @return string[] The strings matching the given pattern
	 */
	public static function extractStrings(string $string, $pattern): array {

		$substractorPatterns = (array) $pattern;

		$result = [];

		foreach ($substractorPatterns as $pattern) {
			$regExPattern = self::substractorToRegEx($pattern);

			preg_match_all("/$regExPattern/", $string, $matches);

			if (is_array($matches)) {
				$result = array_merge($result, $matches[0]);
			}
		}

		return $result;

	}

	/**
	 * Indicates whether or not a string fully matches a given Substractor pattern.
	 *
	 * @param string $string The string of words to be matched.
	 * @param string $pattern The Substractor pattern to match against.
	 * @return boolean True if the pattern matches, false otherwise
	 */
	public static function matches(string $string, string $pattern): bool {

		$regExPattern = self::substractorToRegEx($pattern);

		preg_match("/$regExPattern/", $string, $result);

		return (boolean) $result;

	}

}
