<?php
/**
 * Load a DOM document from a HTML5 string or file
 *
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @copyright Copyright (c) 2009-2018 Bastian Feder, Thomas Weinert
 */

namespace FluentDOM\HTML5 {

    use FluentDOM\DOM\Document;
    use FluentDOM\Exceptions\InvalidSource\TypeFile;
    use FluentDOM\Exceptions\InvalidSource\TypeString;
    use FluentDOM\HTML5\Exceptions\FluentLoaderExceptions;
    use FluentDOM\Loadable;
    use FluentDOM\Loader\Options;
    use FluentDOM\Loader\Result;
    use FluentDOM\Loader\Supports;
    use Masterminds\HTML5 as HTML5Support;

    /**
     * TODO: Needs to be extracted in more seperate files for better understanding
     *
     * Load a DOM document from a HTML5 string or file
     */
    class Loader implements Loadable{
        use Supports;

        const IS_FRAGMENT = 'is_fragment';

        const DISABLE_HTML_NAMESPACE = 'disable_html_ns';

        const IMPLICIT_NAMESPACES = 'implicit_namespaces';

        /**
         * Returns the supported loaders
         *
         * @return string[]
         */
        public function getSupported(): array{
            return ['html5', 'text/html5', 'html5-fragment', 'text/html5-fragment'];
        }

        /**
         * Loads DOMDocument into memory from File or from Type Text
         *
         * @param mixed  $source
         * @param string $contentType
         * @param array  $options
         *
         * @return \DOMDocument|Result
         *
         * @throws FluentLoaderExceptions
         * @throws TypeFile
         * @throws TypeString
         */
        public function load($source, string $contentType, $options = []){
            if ($this->supports($contentType)) {
                $html5 = new HTML5Support();
                $settings = $this->getOptions($options);
                if ($this->isFragment($contentType, $settings)) {
                    return $this->returnHTML5Fragment($source, $html5, $settings);
                }
                $settings->isAllowed($sourceType = $settings->getSourceType($source));
                $document = $this->loadHTML($source, $sourceType, $html5, $settings);

                return $document;
            } else {
                throw new FluentLoaderExceptions('The loader for ' . $contentType . ' is not supported');
            }
        }

        /**
         * Checks if the variable $contentType is a fragment
         *
         * @param string  $contentType
         * @param Options $options
         *
         * @return bool
         */
        private function isFragment(string $contentType, $options){
            return ($contentType === 'html5-fragment' || $contentType === 'text/html5-fragment' || $options[self::IS_FRAGMENT]);
        }

        /**
         * Gets the options
         *
         * @param $options
         *
         * @return Options
         */
        private function getOptions($options){
            $result = new Options($options, [Options::CB_IDENTIFY_STRING_SOURCE => function ($source){
                return $this->startsWith($source, '<');
            }]);
            return $result;
        }

        /**
         * Loads a fragment
         *
         * TODO: NOT TESTED!! Write test for this method
         *
         * @param string $source
         * @param string $contentType
         * @param array  $options
         *
         * @return \DOMDocumentFragment|\FluentDOM\DOM\DocumentFragment|NULL
         */
        public function loadFragment($source, string $contentType, $options = []){
            if ($this->supports($contentType)) {
                $html5 = new HTML5Support();
                return $html5->loadHTMLFragment($source, $this->getLibraryOptions($this->getOptions($options)));
            }
            return NULL;
        }

        /**
         *
         * @param $settings
         *
         * @return array
         */
        private function getLibraryOptions($settings){
            $libraryOptions = ['disable_html_ns' => (bool)$settings[self::DISABLE_HTML_NAMESPACE]];
            if (\is_array($settings[self::IMPLICIT_NAMESPACES])) {
                $libraryOptions = $settings[self::IMPLICIT_NAMESPACES];
            }
            return $libraryOptions;
        }

        /**
         * @param $source   string
         * @param $html5    HTML5Support
         * @param $settings Options
         *
         * @return Result
         */
        private function returnHTML5Fragment($source, $html5, $settings){
            $document = new Document();
            $document->append($html5->loadHTMLFragment($source, $this->getLibraryOptions($settings)));
            $document->registerNamespace('html', 'http://www.w3.org/1999/xhtml');
            return new Result($document, 'text/html5-fragment', $document->evaluate('/node()'));
        }

        /**
         * @param $source     string
         * @param $sourceType string
         * @param $html5      HTML5Support
         * @param $settings   Options
         *
         * @return \DOMDocument A DOM document. DOM is part of libxml, which is included with
         *         almost all distributions of PHP.
         */
        private function loadFromStringOrFile($source, $sourceType, $html5, $settings){
            switch ($sourceType) {
                case Options::IS_FILE :
                    //TODO: NOT TESTED!! Write test for this case
                    $document = $html5->loadHTMLFile($source, $this->getLibraryOptions($settings));
                    break;
                case Options::IS_STRING :
                default :
                    $document = $html5->loadHTML($source, $this->getLibraryOptions($settings));
            }
            return $document;
        }

        /**
         * @param $document
         *
         * @return Document
         */
        private function handleDOMDocument($document): Document{
            if (!$document instanceof Document) {
                $import = new Document();
                if ($document->documentElement instanceof \DOMElement) {
                    $import->appendChild($import->importNode($document->documentElement, TRUE));
                }
                $document = $import;
            }
            return $document;
        }

        /**
         * @param $source     string
         * @param $sourceType string
         * @param $html5      HTML5Support
         * @param $settings   Options
         *
         * @return \DOMDocument|Document
         */
        private function loadHTML($source, $sourceType, $html5, $settings){
            $document = $this->loadFromStringOrFile($source, $sourceType, $html5, $settings);
            $document = $this->handleDOMDocument($document);
            $document->registerNamespace('html', 'http://www.w3.org/1999/xhtml');
            return $document;
        }
    }
}
