<?php
ini_set('show_errors', 1);
error_reporting(E_ALL);

require dirname(__FILE__).'/word_cloud.php';

$font = dirname(__FILE__).'/Arial.ttf';
$width = 600;
$height = 600;
$full_text = file_get_contents(dirname(__FILE__).'/test/example_text.txt');

$cloud = new WordCloud($width, $height, $font);
$cloud->parse_text($full_text, TRUE, TRUE);
$cloud->set_palette(Palette::get_random_palette($cloud->get_image()));
$cloud->set_text_size(10, 60);
$cloud->set_word_limit(75);
$cloud->set_vertical_frequency(FrequencyTable::WORDS_MAINLY_HORIZONTAL);
$cloud->render();
$cloud->output();

/*
// Render the cloud in a temporary file, and return its base64-encoded content
$file = tempnam(getcwd(), 'img');
imagepng($cloud->get_image(), $file);
$img64 = base64_encode(file_get_contents($file));
unlink($file);
imagedestroy($cloud->get_image());
?>

<img usemap="#mymap" src="data:image/png;base64,<?php echo $img64 ?>" border="0"/>
<map name="mymap">
<?php foreach($cloud->get_image_map() as $map): ?>
<area shape="rect" coords="<?php echo $map[1]->get_map_coords() ?>" onclick="alert('You clicked: <?php echo $map[0] ?>');" />
<?php endforeach ?>
</map>