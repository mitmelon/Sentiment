<?php
namespace Sentiment;
/**
 * Sentiment is a sentiment classifier. It uses a model of words that are categorised as positive, negative or neutral, and a naive bayes algorithm to calculate sentiment. To improve accuracy, Sentiment removes 'noise' words
 *
 *  @author Mitnets Technologies <support@mitnets.com>
 *  @version 1.0.0
 */

class Sentiment
{

    /**
     * Location of the dictionary files
     * @var str
     */
    private $dataFolder = '';

    /**
     * List of tokens to ignore
     * @var array
     */
    private $ignoreList = array();

    /**
     * List of words with negative prefixes, e.g. isn't, arent't
     * @var array
     */
    private $negPrefixList = array();

    /**
     * Storage of cached dictionaries
     * @var array
     */
    private $dictionary = array();

    /**
     * Min length of a token for it to be taken into consideration
     * @var int
     */
    private $minTokenLength = 1;

    /**
     * Max length of a taken for it be taken into consideration
     * @var int
     */
    private $maxTokenLength = 15;

    /**
     * Classification of opinions
     * @var array
     */
    private $classes = array('pos', 'neg', 'neu');

    /**
     * Token score per class
     * @var array
     */
    private $classTokCounts = array(
        'pos' => 0,
        'neg' => 0,
        'neu' => 0
    );

    /**
     * Analyzed text score per class
     * @var array
     */
    private $classDocCounts = array(
        'pos' => 0,
        'neg' => 0,
        'neu' => 0
    );

    /**
     * Number of tokens in a text
     * @var int
     */
    private $tokCount = 0;

    /**
     * Number of analyzed texts
     * @var int
     */
    private $docCount = 0;

    /**
     * Implication that the analyzed text has 1/3 chance of being in either of the 3 categories
     * @var array
     */
    private $prior = array(
        'pos' => 0.333333333333,
        'neg' => 0.333333333333,
        'neu' => 0.333333333334
    );

    /**
     * Class constructor
     * @param str $dataFolder base folder
     * Sets defaults and loads/caches dictionaries
     */
    public function __construct($dataFolder = false)
    {

        //set the base folder for the data models
        $this->setDataFolder($dataFolder);

        //load and cache directories, get ignore and prefix lists
        $this->loadDefaults();
    }

    /**
     * Get scores for each class
     *
     * @param str $sentence Text to analyze
     * @return int Score
     */
    public function score($sentence)
    {

        //For each negative prefix in the list
        foreach ($this->negPrefixList as $negPrefix) {

            //Search if that prefix is in the document
            if (strpos($sentence, $negPrefix) !== false) {
                //Reove the white space after the negative prefix
                $sentence = str_replace($negPrefix . ' ', $negPrefix, $sentence);
            }
        }

        //Tokenise Document
        $tokens = $this->_getTokens($sentence);
        // calculate the score in each category

        $total_score = 0;

        //Empty array for the scores for each of the possible categories
        $scores = array();

        //Loop through all of the different classes set in the $classes variable
        foreach ($this->classes as $class) {

            //In the scores array add another dimention for the class and set it's value to 1. EG $scores->neg->1
            $scores[$class] = 1;

            //For each of the individual words used loop through to see if they match anything in the $dictionary
            foreach ($tokens as $token) {

                //If statement so to ignore tokens which are either too long or too short or in the $ignoreList
                if (strlen($token) > $this->minTokenLength && strlen($token) < $this->maxTokenLength && !in_array($token, $this->ignoreList)) {
                    //If dictionary[token][class] is set
                    if (isset($this->dictionary[$token][$class])) {
                        //Set count equal to it
                        $count = $this->dictionary[$token][$class];
                    } else {
                        $count = 0;
                    }

                    //Score[class] is calcumeted by $scores[class] x $count +1 divided by the $classTokCounts[class] + $tokCount
                    $scores[$class] *= ($count + 1);
                }
            }

            //Score for this class is the prior probability multiplyied by the score for this class
            $scores[$class] = $this->prior[$class] * $scores[$class];
        }

        //Makes the scores relative percents
        foreach ($this->classes as $class) {
            $total_score += $scores[$class];
        }

        foreach ($this->classes as $class) {
            $scores[$class] = round($scores[$class] / $total_score, 3);
        }

        //Sort array in reverse order
        arsort($scores);

        return $scores;
    }

    /**
     * Get the class of the text based on it's score
     *
     * @param str $sentence
     * @return str pos|neu|neg
     */
    public function categorise($sentence)
    {
        $scores = $this->score($sentence);

        //Classification is the key to the scores array
        $classification = key($scores);

        return $classification;
    }

    /**
     * Load and cache dictionary
     *
     * @param str $class
     * @return boolean
     */
    public function setDictionary($class)
    {
        /**
         *  For some people this file extention causes some problems!
         */
        $fn = "{$this->dataFolder}data.{$class}.php";

        if (file_exists($fn)) {
            $temp = file_get_contents($fn);
            $words = unserialize($temp);
        } else {
            echo 'File does not exist: ' . $fn;
        }

        //Loop through all of the entries
        foreach ($words as $word) {
            $this->docCount++;
            $this->classDocCounts[$class]++;

            //Trim word
            $word = trim($word);

            //If this word isn't already in the dictionary with this class
            if (!isset($this->dictionary[$word][$class])) {

                //Add to this word to the dictionary and set counter value as one. This function ensures that if a word is in the text file more than once it still is only accounted for one in the array
                $this->dictionary[$word][$class] = 1;
            }//Close If statement

            $this->classTokCounts[$class]++;
            $this->tokCount++;
        }//Close while loop going through everyline in the text file

        return true;
    }

    /**
     * Set the base folder for loading data models
     * @param str  $dataFolder base folder
     * @param bool $loadDefaults true - load everything by default | false - just change the directory
     */
    public function setDataFolder($dataFolder = false, $loadDefaults = false)
    {
        //if $dataFolder not provided, load default, else set the provided one
        if ($dataFolder == false) {
            $this->dataFolder = __DIR__ . '/model/';
        } else {
            if (file_exists($dataFolder)) {
                $this->dataFolder = $dataFolder;
            } else {
                echo 'Error: could not find the directory - '.$dataFolder;
            }
        }

        //load default directories, ignore and prefixe lists
        if ($loadDefaults !== false) {
            $this->loadDefaults();
        }
    }

    /**
     * Load and cache directories, get ignore and prefix lists
     */
    private function loadDefaults()
    {
        // Load and cache dictionaries
        foreach ($this->classes as $class) {
            if (!$this->setDictionary($class)) {
                echo "Error: Dictionary for class '$class' could not be loaded";
            }
        }

        if (!isset($this->dictionary) || empty($this->dictionary)) {
            echo 'Error: Dictionaries not set';
        }

        //Run function to get ignore list
        $this->ignoreList = $this->getList('ign');

        //If ingnoreList not get give error message
        if (!isset($this->ignoreList)) {
            echo 'Error: Ignore List not set';
        }

        //Get the list of negative prefixes
        $this->negPrefixList = $this->getList('prefix');

        //If neg prefix list not set give error
        if (!isset($this->negPrefixList)) {
            echo 'Error: Ignore List not set';
        }
    }

    /**
     * Break text into tokens
     *
     * @param str $string	String being broken up
     * @return array An array of tokens
     */
    private function _getTokens($string)
    {

        // Replace line endings with spaces
        $string = str_replace("\r\n", " ", $string);

        //Clean the string so is free from accents
        $string = $this->_cleanString($string);

        //Make all texts lowercase as the database of words in in lowercase
        $string = strtolower($string);

        //Break string into individual words using explode putting them into an array
        $matches = explode(" ", $string);

        //Return array with each individual token
        return $matches;
    }

    /**
     * Load and cache additional word lists
     *
     * @param str $type
     * @return array
     */
    public function getList($type)
    {
        //Set up empty word list array
        $wordList = array();

        $fn = "{$this->dataFolder}data.{$type}.php";
        ;
        if (file_exists($fn)) {
            $temp = file_get_contents($fn);
            $words = unserialize($temp);
        } else {
            return 'File does not exist: ' . $fn;
        }

        //Loop through results
        foreach ($words as $word) {
            //remove any slashes
            $word = stripcslashes($word);
            //Trim word
            $trimmed = trim($word);

            //Push results into $wordList array
            array_push($wordList, $trimmed);
        }
        //Return $wordList
        return $wordList;
	}
	/**
     * Train existing model with word list
	 * @param string $class model name to train 'pos', 'neg', 'neu'
	 * @return array $wordList training set which must be in one dimensional array
	 */
    public function training($class, array $wordList)
    {
        $fn = "{$this->dataFolder}data.{$class}.php";
        ;
        if (is_array($wordList)) {
            if (count($wordList) === count($wordList, COUNT_RECURSIVE)) {
                //Only one dimensional array allowed for training
                if (file_exists($fn)) {
                    $temp = file_get_contents($fn);
                    $words = unserialize($temp);
                    $ignore = [];
                    foreach ($wordList as $wt) {
                        if (count(array_intersect_key(array_flip($wordList), $words)) === count($wordList)) {
                            array_push($words, $wt);
                        } else {
                            $ignore[] = $wt;
                        }
                    }
                    $trained = serialize($words);
                    file_put_contents($fn, $trained);
                    return "Training model completed on {$class} with ignored training set of ".implode(', ', $ignore);
                } else {
                    return 'File does not exist: ' . $fn;
                }
            } else {
                return 'Only one dimensional array of training data set allowed.';
            }
        } else {
            return 'Training data set must be an array.';
        }
    }
    /**
     * Checks to see if a string is utf8 encoded.
	 * NOTE: This function checks for 5-Byte sequences, UTF8 has Bytes Sequences with a maximum length of 4.
	 * @param string $str The string to be checked
	 * @return bool True if $str fits a UTF-8 model, false otherwise.
	 */
    public function seems_utf8($str)
    {
        mbstring_binary_safe_encoding();
        $length = strlen($str);
        reset_mbstring_encoding();
        for ($i=0; $i < $length; $i++) {
            $c = ord($str[$i]);
            if ($c < 0x80) {
                $n = 0;
            } // 0bbbbbbb
            elseif (($c & 0xE0) == 0xC0) {
                $n=1;
            } // 110bbbbb
            elseif (($c & 0xF0) == 0xE0) {
                $n=2;
            } // 1110bbbb
            elseif (($c & 0xF8) == 0xF0) {
                $n=3;
            } // 11110bbb
            elseif (($c & 0xFC) == 0xF8) {
                $n=4;
            } // 111110bb
            elseif (($c & 0xFE) == 0xFC) {
                $n=5;
            } // 1111110b
            else {
                return false;
            } // Does not match any model
        for ($j=0; $j<$n; $j++) { // n bytes matching 10bbbbbb follow ?
            if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80)) {
                return false;
            }
        }
        }
        return true;
    }
    /**
     * Function to clean a string so all characters with accents are turned into ASCII characters. EG: ‡ = a
     *
     * @param str $string
     * @return str
     */
    private function _cleanString($string)
    {
        if (!preg_match('/[\x80-\xff]/', $string)) {
            return $string;
        }
        if (seems_utf8($string)) {
            $chars = array(
                // Decompositions for Latin-1 Supplement
                'ª' => 'a', 'º' => 'o',
                'À' => 'A', 'Á' => 'A',
                'Â' => 'A', 'Ã' => 'A',
                'Ä' => 'A', 'Å' => 'A',
                'Æ' => 'AE','Ç' => 'C',
                'È' => 'E', 'É' => 'E',
                'Ê' => 'E', 'Ë' => 'E',
                'Ì' => 'I', 'Í' => 'I',
                'Î' => 'I', 'Ï' => 'I',
                'Ð' => 'D', 'Ñ' => 'N',
                'Ò' => 'O', 'Ó' => 'O',
                'Ô' => 'O', 'Õ' => 'O',
                'Ö' => 'O', 'Ù' => 'U',
                'Ú' => 'U', 'Û' => 'U',
                'Ü' => 'U', 'Ý' => 'Y',
                'Þ' => 'TH','ß' => 's',
                'à' => 'a', 'á' => 'a',
                'â' => 'a', 'ã' => 'a',
                'ä' => 'a', 'å' => 'a',
                'æ' => 'ae','ç' => 'c',
                'è' => 'e', 'é' => 'e',
                'ê' => 'e', 'ë' => 'e',
                'ì' => 'i', 'í' => 'i',
                'î' => 'i', 'ï' => 'i',
                'ð' => 'd', 'ñ' => 'n',
                'ò' => 'o', 'ó' => 'o',
                'ô' => 'o', 'õ' => 'o',
                'ö' => 'o', 'ø' => 'o',
                'ù' => 'u', 'ú' => 'u',
                'û' => 'u', 'ü' => 'u',
                'ý' => 'y', 'þ' => 'th',
                'ÿ' => 'y', 'Ø' => 'O',
                // Decompositions for Latin Extended-A
                'Ā' => 'A', 'ā' => 'a',
                'Ă' => 'A', 'ă' => 'a',
                'Ą' => 'A', 'ą' => 'a',
                'Ć' => 'C', 'ć' => 'c',
                'Ĉ' => 'C', 'ĉ' => 'c',
                'Ċ' => 'C', 'ċ' => 'c',
                'Č' => 'C', 'č' => 'c',
                'Ď' => 'D', 'ď' => 'd',
                'Đ' => 'D', 'đ' => 'd',
                'Ē' => 'E', 'ē' => 'e',
                'Ĕ' => 'E', 'ĕ' => 'e',
                'Ė' => 'E', 'ė' => 'e',
                'Ę' => 'E', 'ę' => 'e',
                'Ě' => 'E', 'ě' => 'e',
                'Ĝ' => 'G', 'ĝ' => 'g',
                'Ğ' => 'G', 'ğ' => 'g',
                'Ġ' => 'G', 'ġ' => 'g',
                'Ģ' => 'G', 'ģ' => 'g',
                'Ĥ' => 'H', 'ĥ' => 'h',
                'Ħ' => 'H', 'ħ' => 'h',
                'Ĩ' => 'I', 'ĩ' => 'i',
                'Ī' => 'I', 'ī' => 'i',
                'Ĭ' => 'I', 'ĭ' => 'i',
                'Į' => 'I', 'į' => 'i',
                'İ' => 'I', 'ı' => 'i',
                'Ĳ' => 'IJ','ĳ' => 'ij',
                'Ĵ' => 'J', 'ĵ' => 'j',
                'Ķ' => 'K', 'ķ' => 'k',
                'ĸ' => 'k', 'Ĺ' => 'L',
                'ĺ' => 'l', 'Ļ' => 'L',
                'ļ' => 'l', 'Ľ' => 'L',
                'ľ' => 'l', 'Ŀ' => 'L',
                'ŀ' => 'l', 'Ł' => 'L',
                'ł' => 'l', 'Ń' => 'N',
                'ń' => 'n', 'Ņ' => 'N',
                'ņ' => 'n', 'Ň' => 'N',
                'ň' => 'n', 'ŉ' => 'n',
                'Ŋ' => 'N', 'ŋ' => 'n',
                'Ō' => 'O', 'ō' => 'o',
                'Ŏ' => 'O', 'ŏ' => 'o',
                'Ő' => 'O', 'ő' => 'o',
                'Œ' => 'OE','œ' => 'oe',
                'Ŕ' => 'R','ŕ' => 'r',
                'Ŗ' => 'R','ŗ' => 'r',
                'Ř' => 'R','ř' => 'r',
                'Ś' => 'S','ś' => 's',
                'Ŝ' => 'S','ŝ' => 's',
                'Ş' => 'S','ş' => 's',
                'Š' => 'S', 'š' => 's',
                'Ţ' => 'T', 'ţ' => 't',
                'Ť' => 'T', 'ť' => 't',
                'Ŧ' => 'T', 'ŧ' => 't',
                'Ũ' => 'U', 'ũ' => 'u',
                'Ū' => 'U', 'ū' => 'u',
                'Ŭ' => 'U', 'ŭ' => 'u',
                'Ů' => 'U', 'ů' => 'u',
                'Ű' => 'U', 'ű' => 'u',
                'Ų' => 'U', 'ų' => 'u',
                'Ŵ' => 'W', 'ŵ' => 'w',
                'Ŷ' => 'Y', 'ŷ' => 'y',
                'Ÿ' => 'Y', 'Ź' => 'Z',
                'ź' => 'z', 'Ż' => 'Z',
                'ż' => 'z', 'Ž' => 'Z',
                'ž' => 'z', 'ſ' => 's',
                // Decompositions for Latin Extended-B
                'Ș' => 'S', 'ș' => 's',
                'Ț' => 'T', 'ț' => 't',
                // Euro Sign
                '€' => 'E',
                // GBP (Pound) Sign
                '£' => '',
                // Vowels with diacritic (Vietnamese)
                // unmarked
                'Ơ' => 'O', 'ơ' => 'o',
                'Ư' => 'U', 'ư' => 'u',
                // grave accent
                'Ầ' => 'A', 'ầ' => 'a',
                'Ằ' => 'A', 'ằ' => 'a',
                'Ề' => 'E', 'ề' => 'e',
                'Ồ' => 'O', 'ồ' => 'o',
                'Ờ' => 'O', 'ờ' => 'o',
                'Ừ' => 'U', 'ừ' => 'u',
                'Ỳ' => 'Y', 'ỳ' => 'y',
                // hook
                'Ả' => 'A', 'ả' => 'a',
                'Ẩ' => 'A', 'ẩ' => 'a',
                'Ẳ' => 'A', 'ẳ' => 'a',
                'Ẻ' => 'E', 'ẻ' => 'e',
                'Ể' => 'E', 'ể' => 'e',
                'Ỉ' => 'I', 'ỉ' => 'i',
                'Ỏ' => 'O', 'ỏ' => 'o',
                'Ổ' => 'O', 'ổ' => 'o',
                'Ở' => 'O', 'ở' => 'o',
                'Ủ' => 'U', 'ủ' => 'u',
                'Ử' => 'U', 'ử' => 'u',
                'Ỷ' => 'Y', 'ỷ' => 'y',
                // tilde
                'Ẫ' => 'A', 'ẫ' => 'a',
                'Ẵ' => 'A', 'ẵ' => 'a',
                'Ẽ' => 'E', 'ẽ' => 'e',
                'Ễ' => 'E', 'ễ' => 'e',
                'Ỗ' => 'O', 'ỗ' => 'o',
                'Ỡ' => 'O', 'ỡ' => 'o',
                'Ữ' => 'U', 'ữ' => 'u',
                'Ỹ' => 'Y', 'ỹ' => 'y',
                // acute accent
                'Ấ' => 'A', 'ấ' => 'a',
                'Ắ' => 'A', 'ắ' => 'a',
                'Ế' => 'E', 'ế' => 'e',
                'Ố' => 'O', 'ố' => 'o',
                'Ớ' => 'O', 'ớ' => 'o',
                'Ứ' => 'U', 'ứ' => 'u',
                // dot below
                'Ạ' => 'A', 'ạ' => 'a',
                'Ậ' => 'A', 'ậ' => 'a',
                'Ặ' => 'A', 'ặ' => 'a',
                'Ẹ' => 'E', 'ẹ' => 'e',
                'Ệ' => 'E', 'ệ' => 'e',
                'Ị' => 'I', 'ị' => 'i',
                'Ọ' => 'O', 'ọ' => 'o',
                'Ộ' => 'O', 'ộ' => 'o',
                'Ợ' => 'O', 'ợ' => 'o',
                'Ụ' => 'U', 'ụ' => 'u',
                'Ự' => 'U', 'ự' => 'u',
                'Ỵ' => 'Y', 'ỵ' => 'y',
                // Vowels with diacritic (Chinese, Hanyu Pinyin)
                'ɑ' => 'a',
                // macron
                'Ǖ' => 'U', 'ǖ' => 'u',
                // acute accent
                'Ǘ' => 'U', 'ǘ' => 'u',
                // caron
                'Ǎ' => 'A', 'ǎ' => 'a',
                'Ǐ' => 'I', 'ǐ' => 'i',
                'Ǒ' => 'O', 'ǒ' => 'o',
                'Ǔ' => 'U', 'ǔ' => 'u',
                'Ǚ' => 'U', 'ǚ' => 'u',
                // grave accent
                'Ǜ' => 'U', 'ǜ' => 'u',
                );
            // Used for locale-specific rules
            $locale = get_locale();
        
            if ('de_DE' == $locale || 'de_DE_formal' == $locale || 'de_CH' == $locale || 'de_CH_informal' == $locale) {
                $chars[ 'Ä' ] = 'Ae';
                $chars[ 'ä' ] = 'ae';
                $chars[ 'Ö' ] = 'Oe';
                $chars[ 'ö' ] = 'oe';
                $chars[ 'Ü' ] = 'Ue';
                $chars[ 'ü' ] = 'ue';
                $chars[ 'ß' ] = 'ss';
            } elseif ('da_DK' === $locale) {
                $chars[ 'Æ' ] = 'Ae';
                $chars[ 'æ' ] = 'ae';
                $chars[ 'Ø' ] = 'Oe';
                $chars[ 'ø' ] = 'oe';
                $chars[ 'Å' ] = 'Aa';
                $chars[ 'å' ] = 'aa';
            } elseif ('ca' === $locale) {
                $chars[ 'l·l' ] = 'll';
            } elseif ('sr_RS' === $locale || 'bs_BA' === $locale) {
                $chars[ 'Đ' ] = 'DJ';
                $chars[ 'đ' ] = 'dj';
            }
        
            $string = strtr($string, $chars);
        } else {
            $chars = array();
            // Assume ISO-8859-1 if not UTF-8
            $chars['in'] = "\x80\x83\x8a\x8e\x9a\x9e"
                    ."\x9f\xa2\xa5\xb5\xc0\xc1\xc2"
                    ."\xc3\xc4\xc5\xc7\xc8\xc9\xca"
                    ."\xcb\xcc\xcd\xce\xcf\xd1\xd2"
                    ."\xd3\xd4\xd5\xd6\xd8\xd9\xda"
                    ."\xdb\xdc\xdd\xe0\xe1\xe2\xe3"
                    ."\xe4\xe5\xe7\xe8\xe9\xea\xeb"
                    ."\xec\xed\xee\xef\xf1\xf2\xf3"
                    ."\xf4\xf5\xf6\xf8\xf9\xfa\xfb"
                    ."\xfc\xfd\xff";
        
            $chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";
        
            $string = strtr($string, $chars['in'], $chars['out']);
            $double_chars = array();
            $double_chars['in'] = array("\x8c", "\x9c", "\xc6", "\xd0", "\xde", "\xdf", "\xe6", "\xf0", "\xfe");
            $double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
            $string = str_replace($double_chars['in'], $double_chars['out'], $string);
        }
        return $string;
    }
}