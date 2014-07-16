<?php

if (!defined('DOKU_INC'))
{
    die;
}

require_once DOKU_INC . 'inc/parser/xhtml.php';

class renderer_plugin_vikonbootstraprenderer extends Doku_Renderer_xhtml
{

    /**
     * Make available as XHTML replacement renderer
     */
    public function canRender($format)
    {
        if ($format == 'xhtml')
        {
            return true;
        }

        return false;
    }

    public function document_end()
    {
        // Finish open section edits.
        while (count($this->sectionedits) > 0)
        {
            if ($this->sectionedits[count($this->sectionedits) - 1][1] <= 1)
            {
                // If there is only one section, do not write a section edit
                // marker.
                array_pop($this->sectionedits);
            }
            else
            {
                $this->finishSectionEdit();
            }
        }

        if (count($this->footnotes) > 0)
        {
            $this->doc .= '<hr />';
            $this->doc .= '<div class="footnotes">' . DOKU_LF;

            foreach ($this->footnotes as $id => $footnote)
            {
                // check its not a placeholder that indicates actual footnote text is elsewhere
                if (substr($footnote, 0, 5) != "@@FNT")
                {

                    // open the footnote and set the anchor and backlink
                    $this->doc .= '<div class="fn">';
                    $this->doc .= '<a href="#fnt__' . $id . '" id="fn__' . $id . '" class="fn_bot">';
                    $this->doc .= $id . ')</a> ' . DOKU_LF;

                    // get any other footnotes that use the same markup
                    $alt = array_keys($this->footnotes, "@@FNT$id");

                    if (count($alt))
                    {
                        foreach ($alt as $ref)
                        {
                            // set anchor and backlink for the other footnotes
                            $this->doc .= ', <a href="#fnt__' . ($ref) . '" id="fn__' . ($ref) . '" class="fn_bot">';
                            $this->doc .= ($ref) . ')</a> ' . DOKU_LF;
                        }
                    }

                    // add footnote markup and close this footnote
                    $this->doc .= $footnote;
                    $this->doc .= '</div>' . DOKU_LF;
                }
            }
            $this->doc .= '</div>' . DOKU_LF;
        }

        // Prepare the TOC
        global $conf;
        if ($this->info['toc'] && is_array($this->toc) && $conf['tocminheads'] && count($this->toc) >= $conf['tocminheads'])
        {
            global $TOC;
            $TOC = $this->toc;
        }

        // make sure there are no empty paragraphs
        $this->doc = preg_replace('#<p>\s*</p>#', '', $this->doc);
    }

    public function p_open()
    {
        $this->doc .= DOKU_LF . '<p class="text-justify">' . DOKU_LF;
    }

    public function footnote_close()
    {
        /** @var $fnid int takes track of seen footnotes, assures they are unique even across multiple docs FS#2841 */
        static $fnid = 0;
        // assign new footnote id (we start at 1)
        $fnid++;

        // recover footnote into the stack and restore old content
        $footnote    = $this->doc;
        $this->doc   = $this->store;
        $this->store = '';

        // check to see if this footnote has been seen before
        $i = array_search($footnote, $this->footnotes);

        if ($i === false)
        {
            // its a new footnote, add it to the $footnotes array
            $this->footnotes[$fnid] = $footnote;
        }
        else
        {
            // seen this one before, save a placeholder
            $this->footnotes[$fnid] = "@@FNT" . ($i);
        }

        // output the footnote reference and link
        $this->doc .= '<sup><a href="#fn__' . $fnid . '" id="fnt__' . $fnid . '" class="fn_top" data-toggle="popover" data-placement="top" data-content="' . $footnote . '">' . $fnid . ')</a></sup>';
    }

    public function quote_open()
    {
        $this->doc .= '<blockquote>' . DOKU_LF;
    }

    public function quote_close()
    {
        $this->doc .= '</blockquote>' . DOKU_LF;
    }

    public function table_open($maxcols = null, $numrows = null, $pos = null)
    {
        global $lang;
        // initialize the row counter used for classes
        $this->_counter['row_counter'] = 0;
        $class                         = 'table';
        if ($pos !== null)
        {
            $class .= ' ' . $this->startSectionEdit($pos, 'table');
        }
        $this->doc .= '<div class="' . $class . '"><table class="table table-striped table-bordered">' .
                      DOKU_LF;
    }

    public function table_close($pos = null)
    {
        $this->doc .= '</table></div>' . DOKU_LF;
        if ($pos !== null)
        {
            $this->finishSectionEdit($pos);
        }
    }

    public function tablethead_open()
    {
        $this->doc .= DOKU_TAB . '<thead>' . DOKU_LF;
    }

    public function tablethead_close()
    {
        $this->doc .= DOKU_TAB . '</thead>' . DOKU_LF;
    }

    public function tablerow_open()
    {
        // initialize the cell counter used for classes
        $this->_counter['cell_counter'] = 0;
        $class                          = 'row' . $this->_counter['row_counter']++;
        $this->doc .= DOKU_TAB . '<tr class="' . $class . '">' . DOKU_LF . DOKU_TAB . DOKU_TAB;
    }

    public function tablerow_close()
    {
        $this->doc .= DOKU_LF . DOKU_TAB . '</tr>' . DOKU_LF;
    }

    public function tableheader_open($colspan = 1, $align = null, $rowspan = 1)
    {
        $class = 'class="col' . $this->_counter['cell_counter']++;
        if (!is_null($align))
        {
            $class .= ' ' . $align . 'align';
        }
        $class .= '"';
        $this->doc .= '<th ' . $class;
        if ($colspan > 1)
        {
            $this->_counter['cell_counter'] += $colspan - 1;
            $this->doc .= ' colspan="' . $colspan . '"';
        }
        if ($rowspan > 1)
        {
            $this->doc .= ' rowspan="' . $rowspan . '"';
        }
        $this->doc .= '>';
    }

    public function tableheader_close()
    {
        $this->doc .= '</th>';
    }

    public function tablecell_open($colspan = 1, $align = null, $rowspan = 1)
    {
        $class = 'class="col' . $this->_counter['cell_counter']++;
        if (!is_null($align))
        {
            $class .= ' text-' . $align ;
        }
        $class .= '"';
        $this->doc .= '<td ' . $class;
        if ($colspan > 1)
        {
            $this->_counter['cell_counter'] += $colspan - 1;
            $this->doc .= ' colspan="' . $colspan . '"';
        }
        if ($rowspan > 1)
        {
            $this->doc .= ' rowspan="' . $rowspan . '"';
        }
        $this->doc .= '>';
    }

    public function tablecell_close()
    {
        $this->doc .= '</td>';
    }
}

