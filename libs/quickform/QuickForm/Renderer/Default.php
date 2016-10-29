<?php
/**
 * @package     HTML_QuickForm
 * @author      Alexey Borzov <avb@php.net>
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @copyright   2001-2011 The PHP Group
 * @license     http://www.php.net/license/3_01.txt PHP License 3.01
 */

/**
 * An abstract base class for QuickForm renderers
 */
require_once 'HTML/QuickForm/Renderer.php';

/**
 * A concrete renderer for HTML_QuickForm, based on QuickForm 2.x built-in one
 *
 * @package     HTML_QuickForm
 * @author      Alexey Borzov <avb@php.net>
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 */
class HTML_QuickForm_Renderer_Default extends HTML_QuickForm_Renderer
{
   /**
    * The HTML of the form
    * @var      string
    * @access   private
    */
    var $_html;

   /**
    * Header Template string
    * @var      string
    * @access   private
    */
    var $_headerTemplate =
        "\n\t<tr>\n\t\t<td style=\"white-space: nowrap; background-color: #CCCCCC;\" align=\"left\" valign=\"top\" colspan=\"2\"><b>{header}</b></td>\n\t</tr>";

   /**
    * Element template string
    * @var      string
    * @access   private
    */
    var $_elementTemplate =
        "\n\t<tr>\n\t\t<td align=\"right\" valign=\"top\"><!-- BEGIN required --><span style=\"color: #ff0000\">*</span><!-- END required --><b>{label}</b></td>\n\t\t<td valign=\"top\" align=\"left\"><!-- BEGIN error --><span style=\"color: #ff0000\">{error}</span><br /><!-- END error -->\t{element}</td>\n\t</tr>";

   /**
    * Form template string
    * @var      string
    * @access   private
    */
    var $_formTemplate =
        "\n<form{attributes}>\n<div>\n{hidden}<table border=\"0\">\n{content}\n</table>\n</div>\n</form>";

   /**
    * Required Note template string
    * @var      string
    * @access   private
    */
    var $_requiredNoteTemplate =
        "\n\t<tr>\n\t\t<td></td>\n\t<td align=\"left\" valign=\"top\">{requiredNote}</td>\n\t</tr>";

   /**
    * Array containing the templates for customised elements
    * @var      array
    * @access   private
    */
    var $_templates = array();

   /**
    * Array containing the templates for group wraps.
    *
    * These templates are wrapped around group elements and groups' own
    * templates wrap around them. This is set by setGroupTemplate().
    *
    * @var      array
    * @access   private
    */
    var $_groupWraps = array();

   /**
    * Array containing the templates for elements within groups
    * @var      array
    * @access   private
    */
    var $_groupTemplates = array();

   /**
    * True if we are inside a group
    * @var      bool
    * @access   private
    */
    var $_inGroup = false;

   /**
    * Array with HTML generated for group elements
    * @var      array
    * @access   private
    */
    var $_groupElements = array();

   /**
    * Template for an element inside a group
    * @var      string
    * @access   private
    */
    var $_groupElementTemplate = '';

   /**
    * HTML that wraps around the group elements
    * @var      string
    * @access   private
    */
    var $_groupWrap = '';

   /**
    * HTML for the current group
    * @var      string
    * @access   private
    */
    var $_groupTemplate = '';

   /**
    * Collected HTML of the hidden fields
    * @var      string
    * @access   private
    */
    var $_hiddenHtml = '';

   /**
    * returns the HTML generated for the form
    *
    * @return string
    */
    function toHtml()
    {
        // _hiddenHtml is cleared in finishForm(), so this only matters when
        // finishForm() was not called (e.g. group::toHtml(), bug #3511)
        return $this->_hiddenHtml . $this->_html;
    }

   /**
    * Called when visiting a form, before processing any form elements
    *
    * @param    HTML_QuickForm  form object being visited
    */
    function startForm(&$form)
    {
        $this->_html = '';
        $this->_hiddenHtml = '';
    }

   /**
    * Called when visiting a form, after processing all form elements
    * Adds required note, form attributes, validation javascript and form content.
    *
    * @param    HTML_QuickForm  form object being visited
    */
    function finishForm(&$form)
    {
        // add a required note, if one is needed
        if (!empty($form->_required) && !$form->_freezeAll) {
            $this->_html .= str_replace('{requiredNote}', $form->getRequiredNote(), $this->_requiredNoteTemplate);
        }
        // add form attributes and content
        $html = str_replace('{attributes}', $form->getAttributes(true), $this->_formTemplate);
        if (strpos($this->_formTemplate, '{hidden}')) {
            $html = str_replace('{hidden}', $this->_hiddenHtml, $html);
        } else {
            $this->_html .= $this->_hiddenHtml;
        }
        $this->_hiddenHtml = '';
        $this->_html = str_replace('{content}', $this->_html, $html);
        // add a validation script
        if ('' != ($script = $form->getValidationScript())) {
            $this->_html = $script . "\n" . $this->_html;
        }
    }

   /**
    * Called when visiting a header element
    *
    * @param    HTML_QuickForm_header   header element being visited
    */
    function renderHeader(&$header)
    {
        $name = $header->getName();
        if (!empty($name) && isset($this->_templates[$name])) {
            $this->_html .= str_replace('{header}', $header->toHtml(), $this->_templates[$name]);
        } else {
            $this->_html .= str_replace('{header}', $header->toHtml(), $this->_headerTemplate);
        }
    }

   /**
    * Helper method for renderElement
    *
    * @param    string      Element name
    * @param    mixed       Element label (if using an array of labels, you should set the appropriate template)
    * @param    bool        Whether an element is required
    * @param    string      Error message associated with the element
    * @access   private
    * @see      renderElement()
    * @return   string      Html for element
    */
    function _prepareTemplate($name, $label, $required, $error)
    {
        if (is_array($label)) {
            $nameLabel = array_shift($label);
        } else {
            $nameLabel = $label;
        }
        if (isset($this->_templates[$name])) {
            $html = str_replace('{label}', $nameLabel, $this->_templates[$name]);
        } else {
            $html = str_replace('{label}', $nameLabel, $this->_elementTemplate);
        }
        if ($required) {
            $html = str_replace('<!-- BEGIN required -->', '', $html);
            $html = str_replace('<!-- END required -->', '', $html);
        } else {
            $html = preg_replace("/([ \t\n\r]*)?<!-- BEGIN required -->.*<!-- END required -->([ \t\n\r]*)?/isU", '', $html);
        }
        if (isset($error)) {
            $html = str_replace('{error}', $error, $html);
            $html = str_replace('<!-- BEGIN error -->', '', $html);
            $html = str_replace('<!-- END error -->', '', $html);
        } else {
            $html = preg_replace("/([ \t\n\r]*)?<!-- BEGIN error -->.*<!-- END error -->([ \t\n\r]*)?/isU", '', $html);
        }
        if (is_array($label)) {
            foreach($label as $key => $text) {
                $key  = is_int($key)? $key + 2: $key;
                $html = str_replace("{label_{$key}}", $text, $html);
                $html = str_replace("<!-- BEGIN label_{$key} -->", '', $html);
                $html = str_replace("<!-- END label_{$key} -->", '', $html);
            }
        }
        if (strpos($html, '{label_')) {
            $html = preg_replace('/\s*<!-- BEGIN label_(\S+) -->.*<!-- END label_\1 -->\s*/is', '', $html);
        }
        return $html;
    }

   /**
    * Renders an element Html
    * Called when visiting an element
    *
    * @param HTML_QuickForm_element form element being visited
    * @param bool                   Whether an element is required
    * @param string                 An error message associated with an element
    */
    function renderElement(&$element, $required, $error)
    {
        if (!$this->_inGroup) {
            $html = $this->_prepareTemplate($element->getName(), $element->getLabel(), $required, $error);
            $this->_html .= str_replace('{element}', $element->toHtml(), $html);

        } elseif (!empty($this->_groupElementTemplate)) {
            $html = str_replace('{label}', $element->getLabel(), $this->_groupElementTemplate);
            if ($required) {
                $html = str_replace('<!-- BEGIN required -->', '', $html);
                $html = str_replace('<!-- END required -->', '', $html);
            } else {
                $html = preg_replace("/([ \t\n\r]*)?<!-- BEGIN required -->.*<!-- END required -->([ \t\n\r]*)?/isU", '', $html);
            }
            $this->_groupElements[] = str_replace('{element}', $element->toHtml(), $html);

        } else {
            $this->_groupElements[] = $element->toHtml();
        }
    }

   /**
    * Renders an hidden element
    * Called when visiting a hidden element
    * 
    * @param HTML_QuickForm_element     form element being visited
    * @access public
    * @return void
    */
    function renderHidden(&$element)
    {
        $this->_hiddenHtml .= $element->toHtml() . "\n";
    }

   /**
    * Called when visiting a raw HTML/text pseudo-element
    *
    * @param  HTML_QuickForm_html   element being visited
    */
    function renderHtml(&$data)
    {
        $this->_html .= $data->toHtml();
    }

   /**
    * Called when visiting a group, before processing any group elements
    *
    * @param HTML_QuickForm_group   group being visited
    * @param bool       Whether a group is required
    * @param string     An error message associated with a group
    */
    function startGroup(&$group, $required, $error)
    {
        $name = $group->getName();
        $this->_groupTemplate        = $this->_prepareTemplate($name, $group->getLabel(), $required, $error);
        $this->_groupElementTemplate = empty($this->_groupTemplates[$name])? '': $this->_groupTemplates[$name];
        $this->_groupWrap            = empty($this->_groupWraps[$name])? '': $this->_groupWraps[$name];
        $this->_groupElements        = array();
        $this->_inGroup              = true;
    }

   /**
    * Called when visiting a group, after processing all group elements
    *
    * @param    HTML_QuickForm_group    group being visited
    */
    function finishGroup(&$group)
    {
        $separator = $group->_separator;
        if (is_array($separator)) {
            $count = count($separator);
            $html  = '';
            for ($i = 0; $i < count($this->_groupElements); $i++) {
                $html .= (0 == $i? '': $separator[($i - 1) % $count]) . $this->_groupElements[$i];
            }
        } else {
            if (is_null($separator)) {
                $separator = '&nbsp;';
            }
            $html = implode((string)$separator, $this->_groupElements);
        }
        if (!empty($this->_groupWrap)) {
            $html = str_replace('{content}', $html, $this->_groupWrap);
        }
        $this->_html   .= str_replace('{element}', $html, $this->_groupTemplate);
        $this->_inGroup = false;
    }

    /**
     * Sets element template
     *
     * @param       string      The HTML surrounding an element
     * @param       string      (optional) Name of the element to apply template for
     */
    function setElementTemplate($html, $element = null)
    {
        if (is_null($element)) {
            $this->_elementTemplate = $html;
        } else {
            $this->_templates[$element] = $html;
        }
    }

    /**
     * Sets template for a group wrapper
     *
     * This template is contained within a group-as-element template
     * set via setTemplate() and contains group's element templates, set
     * via setGroupElementTemplate()
     *
     * @param       string      The HTML surrounding group elements
     * @param       string      Name of the group to apply template for
     */
    function setGroupTemplate($html, $group)
    {
        $this->_groupWraps[$group] = $html;
    }

    /**
     * Sets element template for elements within a group
     *
     * @param       string      The HTML surrounding an element
     * @param       string      Name of the group to apply template for
     */
    function setGroupElementTemplate($html, $group)
    {
        $this->_groupTemplates[$group] = $html;
    }

    /**
     * Sets header template
     *
     * @param       string      The HTML surrounding the header
     */
    function setHeaderTemplate($html)
    {
        $this->_headerTemplate = $html;
    }

    /**
     * Sets form template
     *
     * @param     string    The HTML surrounding the form tags
     */
    function setFormTemplate($html)
    {
        $this->_formTemplate = $html;
    }

    /**
     * Sets the note indicating required fields template
     *
     * @param       string      The HTML surrounding the required note
     */
    function setRequiredNoteTemplate($html)
    {
        $this->_requiredNoteTemplate = $html;
    }

    /**
     * Clears all the HTML out of the templates that surround notes, elements, etc.
     * Useful when you want to use addData() to create a completely custom form look
     */
    function clearAllTemplates()
    {
        $this->setElementTemplate('{element}');
        $this->setFormTemplate("\n\t<form{attributes}>{content}\n\t</form>\n");
        $this->setRequiredNoteTemplate('');
        $this->_templates = array();
    }
}
?>