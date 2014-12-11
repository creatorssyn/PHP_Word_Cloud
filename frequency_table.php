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

/**
 * Table of words and frequencies along with some additional properties.
 */
class FrequencyTable 
{
    const WORDS_HORIZONTAL = 0;
    const WORDS_MAINLY_HORIZONTAL = 1;
    const WORDS_MIXED = 6;
    const WORDS_MAINLY_VERTICAL = 9;
    const WORDS_VERTICAL = 10;

    private $table = array();
    private $rejected_words = array();
    private $rejected_words_list = '/rejected_words_list.txt';
    private $font;
    private $vertical_freq = FrequencyTable::WORDS_MAINLY_HORIZONTAL;
    private $total_occurences = 0;
    private $min_font_size = 16;
    private $max_font_size = 72;

    private $max_count = 1;
    private $min_count = 1;
    private $padding_size = 1.05;
    private $padding_angle = 0;
    private $words_limit;

    /**
     * Construct a new FrequencyTable from a word list and a font
     * @param string $text The text containing the words
     * @param string $font The TTF font file
     * @param integer $vertical_freq Frequency of vertical words (0 - 10, 0 = All horizontal, 10 = All vertical)
     */
    public function __construct($font, $text='', $vertical_freq=FrequencyTable::WORDS_MAINLY_HORIZONTAL, $words_limit=NULL)
    {
        $this->rejected_words = explode(',', file_get_contents(dirname(__FILE__).$this->rejected_words_list));
        $this->words_limit = $words_limit;
        $this->font = $font;
        $this->vertical_freq = $vertical_freq;
        $words = preg_split("/[\n\r\t ]+/", $text);
        $this->create_frequency_table($words);
        $this->process_frequency_table();
    }

    /**
     * Return the current frequency table
     */
    public function get_table()
    {
        $this->process_frequency_table();
        return $this->table;
    }

    /**
     * Insert a word into the frequency table
     * @param string $word the word to insert
     * @param int $count number of instances of the word to insert -- default 1.
     * @param string $title title -- default NULL
     * @param bool $reject if TRUE, reject words in the rejected_words list -- default FALSE
     * @param bool $cleanup if TRUE, remove punctuation and appostrophes from word -- default FALSE
     */
    public function insert_word($word, $count=1, $title=NULL, $reject=FALSE, $cleanup=FALSE)
    {    
        $word = trim(strtolower($word));
        
        // Don't allow multiple words as one
        if(preg_match('/\s/s',  $word) !== 0)
            return;
        
        // Reject unwanted words
        if(($reject) && ((strlen($word) < 3) || (in_array($word, $this->rejected_words))))
            return;
        
        // Clean up word
        if($cleanup) 
            $word = $this->cleanup_word($word);
            
        if($word == "")
            return;
            
        if(array_key_exists($word, $this->table))
        {
            $this->table[$word]->count += $count;
        }
        else 
        {
            $this->table[$word] = new StdClass();
            $this->table[$word]->count = $count;
            $this->table[$word]->word = $word;
            $this->table[$word]->title = $title;
        }
        
        $this->total_occurences += $count; 
        
        if ($this->table[$word]->count > $this->max_count)
        {            
            $this->max_count = $this->table[$word]->count;
        }
    }
  
    /**
     * Creates the frequency table from a text.
     * @param string $words The text containing the words
     */
    private function create_frequency_table($words)
    {
        foreach($words as $key => $word) 
        {
            $this->insert_word($word);
        }
    }

    /**
     * Calculate word frequencies and set additionnal properties of the frequency table
     * @param integer $vertical_freq Frequency of vertical words (0 - 10, 0 = All horizontal, 10 = All vertical)
     */
    private function process_frequency_table()
    {
        arsort($this->table);
        $count = count($this->table);
        $diffcount = (($this->max_count - $this->min_count) != 0)? ($this->max_count - $this->min_count): 1;
        $diffsize = (($this->max_font_size - $this->min_font_size) != 0)? ($this->max_font_size - $this->min_font_size): 1;
        $slope = $diffsize / $diffcount;
        $yintercept = $this->max_font_size - ($slope * $this->max_count);    
      
        // Cut the table so we have only $this->words_limit
        $this->table = array_slice($this->table, 0, $this->words_limit);
      
        foreach($this->table as $key => $val) 
        {
            $font_size = (int)($slope * $this->table[$key]->count + $yintercept);

            // Set min/max val for font size
            if($font_size < $this->min_font_size) 
            {
                $font_size = $this->min_font_size;
            } 
            elseif($font_size > $this->max_font_size) 
            {
              $font_size = $this->max_font_size;
            }
            
            $this->table[$key]->size = $font_size;
            $this->table[$key]->angle = 0;
            
            // Randomly decide if word should be vertical
            if(rand(1, 10) <= $this->vertical_freq) 
                $this->table[$key]->angle = 90;
            
            $this->table[$key]->box = imagettfbbox($this->table[$key]->size * $this->padding_size, $this->table[$key]->angle - $this->padding_angle, $this->font, $key);
        }
    }

    /**
     * Remove unwanted characters from a word
     * @param string $word The word to clenup
     * @return string The cleaned up word
     */
    private function cleanup_word($word)
    {
        $tmp = self::remove_utf8_bom(mb_convert_encoding(trim($word), 'UTF-8', mb_detect_encoding($word)));
            
        // Remove unwanted characters
        $punctuation = array('?', '!', '"');
        
        foreach($punctuation as $p)
          $tmp = str_replace($p, '', $tmp);

        // Remove trailing punctuation
        $punctuation[] = '.';
        $punctuation[] = ',';
        $punctuation[] = ':';
        $punctuation[] = ';';
        
        foreach($punctuation as $p) 
        {
            if(substr($tmp, -1) == $p)
            {
                $tmp = substr($tmp, 0, -1);
            }
        }
        
        return $tmp;
    }

    /**
     * Set the word limit
     * @param int $limit new word limit
     */
    public function set_words_limit($limit)
    {
        $this->words_limit = $limit;
    }

    /**
     * Set the frequency vertical words appear
     * @param int $freq new vertical frequency
     */
    public function set_vertical_freq($freq)
    {
        $this->vertical_freq = $freq;
    }

    /**
     * Set minimum font size
     * @param int $val new min_font_size
     */
    public function set_min_font_size($val) 
    {
        $this->min_font_size = $val;
    }

    /**
     * Set maximum font size
     * @param int $val new max_font_size
     */
    public function set_max_font_size($val) 
    {
        $this->max_font_size = $val;
    }

    /**
     * Get the padding size. I dunno if this is needed anymore.
     */
    public function get_padding_size()
    {
        return $this->padding_size;
    }
    
    /**
     * Remove UTF-8 BOM by jasonhao
     * https://stackoverflow.com/a/15423899
     */
    public static function remove_utf8_bom($text)
    {
        $bom = pack('H*','EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }
}