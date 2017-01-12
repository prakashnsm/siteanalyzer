<?php
include_once('simple_html_dom.php');

class SMS160BY2HTMLParser
{
	var $dom;
	function load($content){
		$html = str_get_html($content);
		$this->dom = new simple_html_dom();
		$this->dom -> load($html);
	}
	function getIds()
    {
		$ids = [];
		foreach ($this->dom->find('.ma-wrpr input') as $input){
		  $id = $input->id;
		  $ids[]=$id;
		}
		return $ids;
	}
}

?>