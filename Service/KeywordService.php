<?php

namespace LITC\KeywordBundle\Service;

use LITC\KeywordBundle\Exception\KeywordException;
use Symfony\Component\HttpKernel\Config\FileLocator;

/**
 * Class KeywordService
 *
 * @package LITC\KeywordBundle\Service
 *
 * @version 1.0
 * @date 07/01/2015
 * @author Antoine DARCHE & Grégory DARCHE (Life in the cloud)
 * @organisation Lifeinthecloud
 * @url http://lifeinthecloud.fr
 */
class KeywordService
{
    // paramètres
    private $_parameters    = array(
        'minlenght' => 2,
        'maxlenght' => 8,
        'regex'     => '/[^.]er|ir|ez$/',
        'dictionary.directory' => 'Resources/dictionary',
    );
    private $dictionary     = array();
    private $buffer         = '';
    public  $keywords       = array();

    private $fileLocator;

    /**
     * Construct the keyword service
     *
     * @param FileLocator $fileLocator
     */
    public function __construct ( FileLocator $fileLocator )
    {
        $this->fileLocator = $fileLocator;

        $this->dictionary['minlenght'] = array();
        $this->dictionary['maxlenght'] = array();
        $this->dictionary['regex']     = array();
        $this->dictionary['user']      = array();
        $this->dictionary['insults']   = $this->load('insults');
        $this->dictionary['common']    = $this->load('common');
    }

    /**
     * Load a database from a gz.php file.
     *
     * @access  protected
     *
     * @param   string     $file    File to load.
     *
     * @return  array
     *
     * @throw   KeywordException
     */
    private function load ( $file = '' )
    {
        $fileResource = $this
            ->fileLocator
            ->locate('@LITCKeywordBundle/'.$this->getParameter('dictionary.directory').'/'.$file.'.gz.php');

        if(file_exists($fileResource))
            return unserialize(gzuncompress(file_get_contents(
                $fileResource
            )));
        else {
            throw new KeywordException(
                'File %s.gz.php is not found in %s directory.',
                0, array($file, $this->getParameter('dictionary.directory')));
        }
    }

    /**
     * Adds a word to banish
     *
     * @param string $words
     */
    public function blacklist ( $words = '' )
    {
        if (is_string($words))
            $words = array($words);

        $words = array_map('trim', $words);
        $words = array_map('mb_strtolower', $words);

        if (!empty($words))
            $this->dictionary['user'] = array_fill_keys(
                $words,
                0
            );
    }

    /**
     * Permet d'ajouter du texte
     *
     * @access  public
     *
     * @param string $text A text
     * @param string $opt  Option
     * <ul>
     *    <li>"+" : it added to the stack</li>
     *    <li>"=" : replace the entire text of the pile</li>
     * </ul>
     */
    public function addText ( $text = '', $opt = '+' )
    {
        // Suppression des espaces en début et fin de chaîne
        $text = trim($text);

        // Suppression des balises HTML
        $text = html_entity_decode($text);
        $text = strip_tags($text);

        // Mise en minuscule
        //$text = strtolower($text);
        $text = mb_strtolower($text, 'UTF-8');

        // On supprime les caratères spéciaux
        $punctuations = array(',', ')', '(', '.','"',
            '<', '>', '!', '?', '/', '-',
            '_', '[', ']', ':', '+', '=', '#',
            '$', '&quot;', '&copy;', '&gt;', '&lt;',
            '&nbsp;', '&trade;', '&reg;', ';',
            chr(10), chr(13), chr(9));
        $text = str_replace($punctuations, ' ', $text);

        // On supprime les doubles espaces
        $text = preg_replace('/ {2,}/si', " ", $text);

        if ($opt == '=')
            $this->buffer = $text;
        else
            $this->buffer .= ' ' . $text;
    }

    /**
     * Retrieves keywords with a single word
     *
     * Returns an array, each key represents
     * a single word and the value is
     * the number of iterations of it
     *
     * @access  public
     *
     * @param integer   $limit  Limit of the number of keywords return
     *
     * @return array    The keywords
     */
    public function get_word ( $limit = null )
    {
        $this->keywords = array();

        $temporary = explode(' ', $this->buffer);

        $count = count($temporary);
        for ($i=0; $i < $count; $i++) {
            if ($this->isValid($temporary[$i]))
                $this->addKeyword($temporary[$i]);
        }

        // on tri par importance
        arsort($this->keywords);

        if (!is_null($limit))
            return array_slice($this->keywords, 0, $limit);

        return $this->keywords;
    }

    /**
     * Retrieves keywords with two words
     *
     * Returns an array, each key represents
     * a composition of two words and the value is
     * the number of iterations of it
     *
     * @access  public
     *
     * @param integer $limit  Limit of the number of keywords return
     *
     * @return array The keywords
     */
    public function get_2word ( $limit = null )
    {
        $this->keywords = array();

        $temporary = explode(' ', $this->buffer);

        $count = count($temporary);
        for ($i=0; $i < $count-1; $i++) {
            if ($this->isValid($temporary[$i]) && $this->isValid($temporary[$i+1]))
                $this->addKeyword($temporary[$i].' '.$temporary[$i+1]);
        }

        // on tri par importance
        arsort($this->keywords);

        if (!is_null($limit))
            return array_slice($this->keywords, 0, $limit);

        return $this->keywords;
    }

    /**
     * Check if a word can be a keyword
     *
     * @access  private
     *
     * @param string $word
     *
     * @return bool
     */
    private function isValid ( $word = '' )
    {
        // si pas trop petit
        if (strlen($word) < $this->getParameter('minlenght')) {
            $this->banned('minlenght', $word);
            return false;
        }

        // si pas trop grand
        if (strlen($word) > $this->getParameter('maxlenght')) {
            $this->banned('maxlenght', $word);
            return false;
        }

        // si correspond au regex personnalisé
        if (preg_match($this->getParameter('regex'), $word)) {
            $this->banned('regex', $word);
            return false;
        }

        // si dans le dictionnaire
        if (array_key_exists ($word, $this->dictionary['user'])) {
            $this->banned('user', $word);
            return false;
        }
        if (array_key_exists ($word, $this->dictionary['common'])) {
            $this->banned('common', $word);
            return false;
        }
        if (array_key_exists ($word, $this->dictionary['insults'])) {
            $this->banned('insults', $word);
            return false;
        }

        return true;
    }

    /**
     * Add a keword to the principal array and add one iteration on it
     * or iterates on the value of the keword
     *
     * @access  private
     *
     * @param string $word
     */
    private function addKeyword ( $word = '' )
    {
        // on ajoute au tableau des keywords
        @$this->keywords[$word] += 1;
    }

    /**
     *  Adds a word to banish
     *
     * @param $dictionary   Type of word
     * @param $word         The word to banish
     */
    private function banned ( $dictionary, $word )
    {
        @$this->dictionary[$dictionary][$word] += 1;
    }

    /**
     * Set a parameter to a class.
     *
     * @access  public
     *
     * @param   string  $key      The key
     * @param   mixed   $value    The value
     *
     */
    public function setParameter ( $key, $value )
    {
        $this->_parameters->setParameter($this, $key, $value);
    }

    /**
     * Get a parameter from a class.
     *
     * @access  public
     *
     * @param   string  $key    The key
     *
     * @return  mixed The parameter
     *
     * @throw   KeywordException If the parameter is not found
     */
    public function getParameter ( $key )
    {
        if(!isset($this->_parameters[$key])) {
            throw new KeywordException(
                'Parameter %s is not found.', 0, array($key)
            );
        }

        return $this->_parameters[$key];
    }
}