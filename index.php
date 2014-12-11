<?php
/**
 * This file is part of the PHP_Word_Cloud project.
 * https://github.com/creatorssyn/PHP_Word_Cloud
 *
 * @author Daniel Barsotti / dan [at] dreamcraft [dot] ch
 * @author Brandon Telle <btelle@creators.com>
 * @license http://creativecommons.org/licenses/by-nc-sa/3.0/
 *          Creative Commons Attribution-NonCommercial-ShareAlike 3.0
 */
 
require dirname(__FILE__).'/word_cloud.php';

/* 
 * Step 1: Create your cloud
 * Below is the process for creating a word cloud
 */
 
// Basic image settings
$font = dirname(__FILE__).'/Arial.ttf';
$full_text = file_get_contents(dirname(__FILE__).'/test/example_text.txt');
$width = 400;
$height = 400;

$cloud = new WordCloud($width, $height, $font);

// Allow the image to exceed the given width and height if needed
$cloud->allow_resize();

// Add words to the cloud
$cloud->parse_text($full_text, TRUE, TRUE);

// Below are optional settings. You could call render() at this point and get the same result.
// Set a palette option. See palette.php for more choices
$cloud->set_palette(Palette::get_random_palette($cloud->get_image()));

// Set min and max text size.
$cloud->set_text_size(16, 72);

// Set the total number of words allowed in the image
$cloud->set_word_limit(75);

// How often should vertical words appear
$cloud->set_vertical_frequency(FrequencyTable::WORDS_MAINLY_HORIZONTAL);

// Render the image
$cloud->render();


/*
 * Step 2: Output the cloud
 * Below is the process for outputting the cloud image
 */

// If no argument is passed to output(), it sends the image directly to the browser with a Content-Type of image/png
//$cloud->output();


// Alternatively, we can store the image to a file
$file = tempnam(getcwd(), 'cloud');
$cloud->output($file);

// And get a base-64 rendering of it
$img64 = base64_encode(file_get_contents($file));
unlink($file);

// And create an interactive map using get_image_map()
?>
<html>
<head>
    <meta charset="utf-8">
    <title>PHP_Word_Cloud</title>
</head>
<body>
    <img usemap="#mymap" src="data:image/png;base64,<?php echo $img64 ?>" border="0"/>
    <map name="mymap">
    <?php foreach($cloud->get_image_map() as $map): ?>
    <area shape="rect" coords="<?php echo $map[1]->get_map_coords() ?>" onclick="alert('You clicked: <?php echo $map[0] ?>');" />
    <?php endforeach; ?>
    </map>
</body>
</html>