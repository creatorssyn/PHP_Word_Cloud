<?php
/**
 * This file is part of the PHP_Word_Cloud project.
 * http://github.com/sixty-nine/PHP_Word_Cloud
 *
 * @author Daniel Barsotti / dan [at] dreamcraft [dot] ch
 * @license http://creativecommons.org/licenses/by-nc-sa/3.0/
 *          Creative Commons Attribution-NonCommercial-ShareAlike 3.0
 */

require dirname(__FILE__).'/box.php';
require dirname(__FILE__).'/mask.php';
require dirname(__FILE__).'/frequency_table.php';
require dirname(__FILE__).'/palette.php';
 
class WordCloud 
{
    const RENDER_LIMIT = 3;
    
    private $width, $height;
    private $font;
    private $mask;
    private $table;
    private $image;
    private $imagecolor;
    private $palette;
    private $allow_resize = FALSE;
    private $redner_count = 0;

    public function __construct($width, $height, $font)
    {
        $this->width = $width;
        $this->height = $height;
        $this->font = $font;
        $this->set_image_color(0, 0, 0, 127);
        $this->mask = new Mask();
        $this->table = new FrequencyTable($font);
        $this->image = imagecreatetruecolor($width, $height);
    }
    
    public function __destruct()
    {
        imagedestroy($this->get_image());
    }

    public function get_image() 
    {
        return $this->image;
    }
  
    public function set_image_color($r, $g, $b, $a)
    {
        $this->imagecolor = array($r, $g, $b, $a);
    }
    
    public function allow_resize($val=TRUE)
    {
        $this->allow_resize = $val;
    }
    
    public function parse_text($text, $reject=FALSE, $cleanup=FALSE)
    {
        if($this->table !== NULL)
        {
            $words = preg_split("/[\n\r\t ]+/", $text);
            
            foreach($words as $word)
            {
                $this->table->insert_word($word, 1, NULL, $reject, $cleanup);
            }
            
            return count($words);
        }
        
        return FALSE;
    }
    
    public function set_text_size($min=NULL, $max=NULL)
    {
        if($min !== NULL && $this->table !== NULL)
            $this->table->set_min_font_size($min);
        
        if($max !== NULL && $this->table !== NULL)
            $this->table->set_max_font_size($max);
    }
    
    public function set_word_limit($limit)
    {
        $this->table->set_words_limit($limit);
    }
    
    public function set_palette($palette)
    {
        $this->palette = $palette;
    }
    
    public function set_vertical_frequency($freq)
    {
        $this->table->set_vertical_freq($freq);
    }

    public function render()
    {
        $this->mask = new Mask();
        $this->render_count++;
    
        //Set the flag to save full alpha channel information (as opposed to single-color transparency) when saving PNG images
        imagealphablending($this->image, FALSE);
        imagesavealpha($this->image, true);

        //behaves identically to imagecolorallocate() with the addition of the transparency parameter alpha
        $trans_colour = imagecolorallocatealpha($this->image, $this->imagecolor[0], $this->imagecolor[1], $this->imagecolor[2], $this->imagecolor[3]);
        imagefill($this->image, 0, 0, $trans_colour);

        $i = 0;
        $positions = array();

        foreach($this->table->get_table() as $key => $val) 
        {
            // Set the center so that vertical words are better distributed
            if ($val->angle == 0) 
            {
                $cx = $this->width /3;
                $cy = $this->height /2;
            }
            else 
            {
                $cx = $this->width /3 + rand(0, $this->width / 10);
                $cy = $this->height /2 + rand(-$this->height/10, $this->height/10);
            }

            // Search the place for the next word
            list($cx, $cy) = $this->mask->search_place($this->image, $cx, $cy, $val->box);

            // Draw the word
            $res['words'][$key] = array(
                'x' => $cx,
                'y' => $cy,
                'angle' => $val->angle,
                'size' => $val->size,
                'color' => $this->palette[$i % count($this->palette)],
                'box' => isset($boxes[$key]) ? $boxes[$key] : '',
            );
            
            //$pad = round((($val->size * $this->table->get_padding_size()) - $val->size)/2);
            //var_dump($pad);
            
            imagettftext($this->image, $val->size, $val->angle, $cx, $cy, $this->palette[$i % count($this->palette)], $this->font, $key);
            $this->mask->add(new Box($cx, $cy, $val->box));
            $i++;
        }
        
        // Crop the image
        list($x1, $y1, $x2, $y2) = $this->mask->get_bounding_box();
        
        if($x1 > 0 && $y1 > 0)
        {
            $image2 = imagecreatetruecolor(abs($x2 - $x1), abs($y2 - $y1));

            //Set the flag to save full alpha channel information (as opposed to single-color transparency) when saving PNG images
            imagesavealpha($image2, true);
            //behaves identically to imagecolorallocate() with the addition of the transparency parameter alpha
            $trans_colour = imagecolorallocatealpha($image2, $this->imagecolor[0],$this->imagecolor[1], $this->imagecolor[2], $this->imagecolor[3]);
            imagefill($image2, 0, 0, $trans_colour);

            if(imagecopy($image2 ,$this->image, 0, 0, $x1, $y1, abs($x2 - $x1), abs($y2 - $y1)))
            {
                imagedestroy($this->image);
                $this->image = $image2;
                
                // Adjust the map to the cropped image
                $this->mask->adjust(-$x1, -$y1);
            }
        }
        elseif($this->allow_resize && $this->render_count < self::RENDER_LIMIT)
        {
            // Start over with a more appropriately-sized canvas, if we're allowed to.
            $this->width = abs($x2-$x1) + 100*$this->render_count;
            $this->height = abs($y2-$y1) + 100*$this->render_count;
            
            imagedestroy($this->image);
            $this->image = imagecreatetruecolor($this->width, $this->height);
            $this->render();
        }
        
        foreach($boxes = $this->get_image_map() as $map) 
        {
            $res['words'][$map[0]]['box'] = $map[1];
        }

        $res['adjust'] = array('dx' => -$x1, 'dy' => -$y1);
        
        return $res;
    }

    public function get_image_map() 
    {
        $words = $this->table->get_table();
        $boxes = $this->mask->get_table();
        
        if (count($boxes) != count($words)) 
        {
            throw new Exception('Error: mask count <> word count');
        }

        $map = array();
        $i = 0;
        foreach($words as $key => $val) 
        {
            $map[] = array($key, $boxes[$i], $val->title);
            $i += 1;
        }

        return $map;
    }
  
    public function output($file=NULL)
    {
        if($file === NULL)
        {
            header('Content-Type: image/png');
        }
        
        imagepng($this->get_image(), $file);
    }
}

