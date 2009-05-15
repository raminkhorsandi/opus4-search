<?php
/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @category    Framework
 * @package     Opus_Search
 * @author      Oliver Marahrens <o.marahrens@tu-harburg.de>
 * @copyright   Copyright (c) 2008, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */

class Opus_Search_Index_Indexer {
	/**
	 * Index variable
	 *
	 * @var Zend_Search_Lucene Index for the search engine
	 * @access private
	 */
	private $entryindex;

	/**
	 * Index path
	 *
	 * @var String Path to the index for the search engine
	 * @access private
	 */
	private $indexPath;

	/**
	 * Constructor
	 *
	 * @throws Zend_Search_Lucene_Exception Exception is thrown when there are problems with the index
	 */
	public function __construct() {
        $registry = Zend_Registry::getInstance();
        $this->indexPath = $registry->get('Zend_LuceneIndexPath');
        try
        {
            $this->entryindex = Zend_Search_Lucene::open($this->indexPath);
        }
        catch (Exception $e) {
            $this->entryindex = Zend_Search_Lucene::create($this->indexPath);
        }
	}

	/**
	 * Stores a document in the Search Engine Index
	 *
	 * @param Opus_Document $doc Model of the document that should be added to the index
	 * @throws Exception Exceptions from Zend_Search_Lucene are thrown
	 * @return void
	 */
	public function addDocumentToEntryIndex(Opus_Document $doc)
	{
    	$returnarray = array();

    	try {
    	    $analyzedDocs = $this->analyzeDocument($doc);
            unset($doc);
    	    foreach ($analyzedDocs as $analyzedDoc) {
			 	if (true === array_key_exists('exception', $analyzedDoc))
			 	{
			 		$returnarray[] = $analyzedDoc['source'] . ' in document ID ' . $analyzedDoc['docid'] . ': ' . $analyzedDoc['exception'];
			 	}
			 	else
			 	{
            	    $indexDoc = new Opus_Search_Index_Document($analyzedDoc);
			 		#echo "Memorybedarf nach Analyse " . memory_get_usage() . "\n";
            	    $this->entryindex->addDocument($indexDoc);
			 		unset($indexDoc);
			 		#echo "Memorybedarf nach Indizierung " . memory_get_usage() . "\n";
			 		$returnarray[] = $analyzedDoc['source'] . ': indexed for document ID ' . $analyzedDoc['docid'];
			 	}
            }
		} catch (Exception $e) {
			throw $e;
        }
        unset($analyzedDoc);
        unset($analyzedDocs);
        return $returnarray;
	}

    /**
     * Removes a document from the Search Engine Index
     *
     * @param Opus_Document $doc Model of the document that should be removed to the index
     * @throws Exception Exceptions from Zend_Search_Lucene are thrown
     * @return void
     */
    public function removeDocumentFromEntryIndex(Opus_Document &$doc)
    {
        try {
            // Weird: some IDs are only found with adding whitespace behind the query...
            // So let's add a space behind the ID.
            $hits = $this->entryindex->find('docid:' . $doc->getId() . ' ');
            foreach ($hits as $hit) {
                $this->entryindex->delete($hit->id);
            }
        } catch (Exception $e) {
            throw $e;
        }
        $this->entryindex->commit();
        $this->entryindex->optimize();
    }

	/**
	 * Finalizes the entry in Search Engine Index
	 *
	 * @return void
	 */
	public function finalize() {
		$this->entryindex->commit();
    	$this->entryindex->optimize();
    	flush();
	}

	private function analyzeDocument(Opus_Document $doc) {
        $returnarray = array();
        $langarray = array();

	    $docarray = $doc->toArray();
	    $document['docid'] = $doc->getId();
	    unset($doc);
	    $document['year'] = $this->getKeyValue(&$docarray, 'CompletedYear');
	    $document['doctype'] = $this->getKeyValue(&$docarray, 'Type');

	    $document['urn'] = $this->getValue(&$docarray, 'IdentifierUrn');
	    $document['isbn'] = $this->getValue(&$docarray, 'IdentifierIsbn');


        $document['author'] = $this->getPersons(&$docarray, 'Author');

        $document['persons'] = '';
        $document['persons'] .= $this->getPersons(&$docarray, 'Advisor');
        $document['persons'] .= $this->getPersons(&$docarray, 'Contributor');
        $document['persons'] .= $this->getPersons(&$docarray, 'Editor');
        $document['persons'] .= $this->getPersons(&$docarray, 'Other');
        $document['persons'] .= $this->getPersons(&$docarray, 'Referee');
        $document['persons'] .= $this->getPersons(&$docarray, 'Translator');

        // Look at all titles of the document
        $titles = '';
        $abstracts = '';
        if (is_array($this->getKeyValue(&$docarray, 'TitleMain')) === true) {
            $titles = $this->getKeyValue(&$docarray, 'TitleMain');
        }
        if (is_array($this->getKeyValue(&$docarray, 'TitleAbstract')) === true) {
            $abstracts = $this->getKeyValue(&$docarray, 'TitleAbstract');
        }
        $document['title'] = '';
        $document['abstract'] = '';
        $document['language'] = '';
        if ($titles !== '') {
            foreach ($titles as $title)
            {
                $document['title'] .= ' ' . $title['Value'];
                $lang = $title['Language'];
                $document['language'] .= ' ' . $lang;
                $document['abstract'] .= ' ' . $this->getAbstract($abstracts, $lang);
                array_push($langarray, $lang);
            }
        }
        // Look if there are non-indexed abstracts left
        if ($abstracts !== '') {
            $not_processed_abstracts = $this->checkAbstractLanguage($abstracts, $langarray);
            foreach ($not_processed_abstracts as $abstract) {
                $document['abstract'] .= ' ' . $abstract;
            }
        }


        // Missing fields
        $document['subject'] = '';
        $document['institute'] = '';

        // index files (each file will get one data set)
        $files = $docarray['File'];
        $file_count = count($files);
        $numberOfIndexableFiles = $file_count;
        foreach ($files as $file)
        {
        	try {
       	        $document['content'] = $this->getFileContent($file);
       	        $document['source'] = $file['PathName'];
       	        unset($document['exception']);
   	        	array_push($returnarray, $document);
            }
            catch (Exception $e) {
        	    $document['source'] = $file['PathName'];
        	    $document['content'] = '';
        	    $document['exception'] = $e->getMessage();
        	    $numberOfIndexableFiles--;
        	    array_push($returnarray, $document);
            }
        }
        // if there is no file (or only non-readable ones) associated with the document, index only metadata
        if ($numberOfIndexableFiles === 0)
        {
            $document['source'] = 'metadata';
            $document['content'] = '';
            unset($document['exception']);
            array_push($returnarray, $document);
        }

        unset($document);
        unset($docarray);
        #print_r($returnarray);
        // return array of documents to index
        return $returnarray;
	}

	private function getAbstract($abstracts, $language) {
        foreach ($abstracts as $abstract)
        {
            if ($abstract['Language'] === $language) {
                return $abstract['Value'];
            }
        }
        return null;
	}

	private function checkAbstractLanguage($abstracts, array $languages) {
        $not_processed = array();
	    foreach ($abstracts as $abstract)
        {
            if (false === in_array($abstract['Language'], $languages)) {
                array_push($not_processed, $abstract['Value']);
            }
        }
        return $not_processed;
	}

	private function getPersons(&$docarray, $roleName) {
	    $returnValue = '';
        if (array_key_exists('Person' . $roleName, $docarray) === false) {
            return $returnValue;
        }
	    $persons = $docarray['Person' . $roleName];
        if (true === @is_array($persons[0])) {
            $person = $persons;
        }
        else {
            $person[0] = array($persons);
        }
        $index = 1;
        foreach ($person as $trans) {
            if (true === array_key_exists('Name', $trans)) {
                $returnValue .= $trans['Name'];
                if (count($person) > 1 && $index !== count($person)) {
                   $returnValue .= '; ';
                }
                $index++;
            }
        }

        return $returnValue;
    }

    private function getValue(&$docarray, $roleName) {
        $returnValue = '';
        if (array_key_exists($roleName, $docarray) === false) {
            return $returnValue;
        }
        $persons = $docarray[$roleName];
        if (true === @is_array($persons[0])) {
            $person = $persons;
        }
        else {
            $person[0] = array($persons);
        }
        foreach ($person as $trans) {
            if (true === array_key_exists('Value', $trans)) {
                $returnValue .= $trans['Value'] . ' ';
            }
        }

        return $returnValue;
    }

    private function getKeyValue(&$docarray, $key) {
        $returnValue = '';
        if (true === array_key_exists($key, $docarray)) {
            $returnValue = $docarray[$key];
        }

        return $returnValue;
    }

    private function getFileContent($file) {
        $fulltext = '';
        //FIXME: Hard coded path!
        $path_prefix = '../workspace/files/' . $file['DocumentId'];
		$mimeType = $file['MimeType'];
		if (substr($mimeType, 0, 9) === 'text/html') {
			$mimeType = 'text/html';
		}
		try {
		    switch ($mimeType)
		    {
			    case 'application/pdf':
				    $fulltext = Opus_Search_Index_FileFormatConverter_PdfDocument::toText($path_prefix . '/' . addslashes($file['PathName']));
				    break;
			    case 'application/postscript':
				    $fulltext = Opus_Search_Index_FileFormatConverter_PsDocument::toText($path_prefix . '/' . addslashes($file['PathName']));
				    break;
			    case 'text/html':
    				$fulltext = Opus_Search_Index_FileFormatConverter_HtmlDocument::toText($path_prefix . '/' . addslashes($file['PathName']));
	    			break;
			    case 'text/plain':
    				$fulltext = Opus_Search_Index_FileFormatConverter_TextDocument::toText($path_prefix . '/' . addslashes($file['PathName']));
	    			break;
		    	default:
			    	throw new Exception('No converter for MIME-Type ' . $mimeType);
		    }
		}
		catch (Exception $e) {
			throw $e;
		}
		return $fulltext;
	}
}