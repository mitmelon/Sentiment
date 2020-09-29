# Sentiment
Sentiment analysis library for mood detections from text.

Sentiment is a sentiment classifier. It uses a model of words that are categorised as positive, negative or neutral, and a naive bayes algorithm to calculate sentiment. To improve accuracy, Sentiment removes 'noise' words. 

## Install:
Use composer to install
```php
composer require mitmelon/sentiment
```

## Initialise :

```php
require_once __DIR__."/vendor/autoload.php";

// Initialize library class
$sentiment = new Sentiment\Sentiment();

```

## Check for sentiment:
```php
//Beutifies your result
if (PHP_SAPI != 'cli') {
	echo "<pre>";
}
//List of sentences to check
$sentences = array('The boy is very bad', 'I love that girl', 'Some people said i need to take a nap');

foreach ($sentences as $sentence) {
	$scores = $sentiment->score($sentence);
    $class = $sentiment->categorise($sentence);

	// return:
	echo "Sentence : $sentence\n";
    echo "Category: $class\n";
    echo "Scores: ";
    print_r($scores);
	echo "\n";
}

```

## Train existing model:
```php
/**
 * You can train the existing model with new word list
 * Train existing model with word list
 * @param string $class model name to train. Anyone on the list - 'pos', 'neg', 'neu'
 * @return array $wordList training set which must be in one dimensional array
*/
$modelName = 'pos';
$wordList = array('love', 'good');// add your wordlist
$train = $sentiment->training($modelName, $wordList);
echo $train;

```

# Future Update

* Similey recognitions

# License

Released under the MIT license.