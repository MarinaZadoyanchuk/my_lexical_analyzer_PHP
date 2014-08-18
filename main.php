<?php
#lexical analyzer PHP

$string_digits = "0123456789";
$string_letters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
$string_punctuation = "!#$%&'()*+,-./:;<=>?@[\\]^_`{|}~\'";
$string_hexdigits = "0123456789abcdefABCDEF";
$string_spaces = "\n\t\r ";

$arr_reserved = array("int", "double", "string", "boolean", "void", "float", "array", 
						"if", "else", "elseif", "while", "do", "die", 
						"for", "foreach", "php", "class", "function",
						"public", "global", "private", "return", "echo",
						"new", "true", "false", "break", "continue", "switch",
						"include_once", "var", "eval"
	);

$string_comments = $string_digits . $string_letters . $string_punctuation;
$string_identifier_start = $string_letters . "_";
$string_identifier = $string_letters . $string_digits . "_";

class Node
{
	public $id;
	public $crosses;
	function __construct($id, $crosses = array())
	{
		$this->id = $id;
		$this->crosses = $crosses;

	}

	function add_cross($alpha, $condition)
	{
		if(!array_key_exists($alpha, $this->crosses))
			$this->crosses[$alpha] = array();
		$this->crosses[$alpha][] = $condition;
	}

	function get_crosses($alpha)
	{
		if(!array_key_exists($alpha, $this->crosses))
			return array();
		else
			return $this->crosses[$alpha];
	}
}

class Automat
{
	public $q0;
	public $fin_states;
	public $currents_states_ids;
	public $all_states;

	function __construct($all_states, $q0, $fin_states)
	{
		$this->all_states = $all_states;
		$this->q0 = $q0;
		$this->fin_states = $fin_states;
		$this->reset();
	}

	function reset() {
		$this->currents_states_ids = array($this->q0->id);
	}

	function go($symbol)
	{
		$new_current_states_ids = array();
		$c = count($this->currents_states_ids);
		for($i = 0; $i<$c; $i++)
		{
			$new_current_states_ids += $this->all_states[$this->currents_states_ids[$i]]->get_crosses($symbol);
		}
		$this->currents_states_ids = $new_current_states_ids;
	}

	function in_final()
	{
		$c = count($this->fin_states);
		for($i = 0; $i<$c; $i++)
		{
			if(in_array($this->fin_states[$i]->id, $this->currents_states_ids))
				return true;
		}
		return false;
	}

	function no_current_states()
	{
		if(empty($this->currents_states_ids))
			return true;
		return false;
	}

	
}

function reserved_words_automat($arr_words)
{
	$c_arr = count($arr_words);
	$q0 = new Node(0);
	$all_states = array($q0);
	$fin_states = array();
	$next_id = 0;
	for($i = 0; $i<$c_arr; $i++)
	{
		$c = strlen($arr_words[$i]);		
		for($j = 0; $j<$c; $j++)
		{										
			if($j==0)
			{
				$all_states[0]->add_cross($arr_words[$i][$j], $next_id+1);
			}
			else
			{
				$next_id += 1;
				$all_states[] = new Node($next_id, array($arr_words[$i][$j] => array($next_id+1)));
			}
			if($j == $c - 1)
			{
				$next_id += 1;
				$node = new Node($next_id);
				$fin_states[] = $node;
				$all_states[] = $node;
			}
		}
	
	}
	return new Automat($all_states, $q0, $fin_states);
}

function comments_automat()
{
	$all_states = array(
		new Node(0, array('#'=>array(1), '/' => array(3))),
		new Node(1, array("\n"=>array(2))),
		new Node(2, array()),
		new Node(3, array('/' => array(1), "*" => array(4))),
		new Node(4, array('*' => array(5))),
		new Node(5, array("*"=>array(5),"/" => array(6))),
		new Node(6, array())
		);

	global $string_digits, $string_letters, $string_punctuation, $string_spaces;
	$spaces = str_replace("\n", '',$string_spaces);
	foreach(str_split($string_digits . $string_letters . $string_punctuation . $spaces) as $i) 
	{
		$all_states[1]->add_cross($i, 1);
	}

	foreach(str_split($string_digits . $string_letters . str_replace("*", "", $string_punctuation) . $string_spaces) as $i) 
	{
		$all_states[4]->add_cross($i, 4);
	}
	foreach(str_split($string_digits . $string_letters . str_replace(array("*", "/"), "", $string_punctuation) . $string_spaces) as $i)
	{
		$all_states[5]-> add_cross($i,4);
	}
	return new Automat($all_states, $all_states[0], array($all_states[2], $all_states[6]));
}

function identifier_automat()
{
	$all_states = array(
		new Node(0, array()),
		new Node(1, array())
		);

	global $string_letters, $string_digits;
	foreach(str_split($string_letters . "_")  as $i)
	{
		$all_states[0]->add_cross($i,1);
	}
	foreach(str_split($string_letters . $string_digits . "_") as $i)
	{
		$all_states[1]->add_cross($i, 1);
	}
	return new Automat($all_states, $all_states[0], array($all_states[1]));

}

function punctuation_automat()
{
	global $string_punctuation;
	$all_states = array(
		new Node(0, array()),
		new Node(1, array())
		);
	foreach(str_split($string_punctuation) as $i)
	{
		$all_states[0]->add_cross($i, 1);
	}
	return new Automat($all_states, $all_states[0], array($all_states[1]));
}

function long_punctuation_automat()
{
	return reserved_words_automat(array('--', '++', '&&', '||', '+=', '-=', '*=', '/=', '==', '!=', '>=', '<=', '.=', '->', '==='));
}

function whitespace_automat()
{
	global $string_spaces;
	$all_states = array(
		new Node(0, array()),
		new Node(1, array())
		);
	foreach(array("\n", "\t", "\r", ' ') as $i)
	{
		$all_states[0]->add_cross($i,1);
		$all_states[1]->add_cross($i,1);
	}
	return new Automat($all_states, $all_states[0], array($all_states[1]));
}

function string_literal_automat()
{
	global $string_spaces, $string_letters, $string_punctuation, $string_digits;
	$all_states = array(
		new Node(0,array('"'=>array(1), "'"=>array(4))),
		new Node(1,array("\\"=>array(2), "\""=>array(3))),
		new Node(2, array()),
		new Node(3, array()),
		new Node(4, array('\\'=>array(5), "'"=>array(3))),
		new Node(5, array())
		);
	foreach(str_split($string_spaces . $string_digits . $string_letters . $string_punctuation) as $i)
	{
		$all_states[2]->add_cross($i,1);
		$all_states[5]->add_cross($i,4);
	}
	foreach(str_split($string_spaces . $string_digits . $string_letters . str_replace(array("'","\\"),"",$string_punctuation)) as $i)
	{
		$all_states[4]->add_cross($i, 4);
	}
	foreach(str_split($string_spaces . $string_digits . $string_letters . str_replace(array("\"","\\"),"",$string_punctuation)) as $i)
	{
		$all_states[1]->add_cross($i, 1);
	}
	return new Automat($all_states, $all_states[0], array($all_states[3]));

}

function number_literal_automat()
{
	global $string_digits, $string_hexdigits;
	$all_states = array(
		new Node(0,array("0"=> array(2))),
		new Node(1,array()),
		new Node(2,array("x"=>array(3))),
		new Node(3,array()),
		new Node(4, array())
		);
	foreach(str_split($string_digits) as $i)
	{
		$all_states[0]->add_cross($i,1);
		$all_states[1]->add_cross($i,1);
	}
	foreach(str_split($string_hexdigits) as $i)
	{
		$all_states[3]->add_cross($i,4);
		$all_states[4]->add_cross($i,4);
	}
	return new Automat($all_states, $all_states[0], array($all_states[1], $all_states[4]));
}

function float_number_literal_automat()
{
	global $string_digits;
	$all_states = array(
		new Node(0,array("."=>array(1))),
		new Node(1,array()),
		new Node(2,array())
		);
	foreach(str_split($string_digits) as $i)
	{
		$all_states[0]->add_cross($i,0);
		$all_states[1]->add_cross($i,2);
		$all_states[2]->add_cross($i,2);
	}
	return new Automat($all_states, $all_states[0], array($all_states[2]));
}

function get_lexem($text, $priorities)
{
	$max_word = null;
	$position = 0;
	$automats_are_empty = false;
	while($position<strlen($text) && !($automats_are_empty))
	{
		$automats_are_empty = true;
		foreach($priorities as $i=>$a)
		{
			$a[0]->go($text[$position]);
			if($a[0]->in_final())
			{
				if($max_word == null || (strlen($max_word[0])<$position+1) || $a[1] > $priorities[$max_word[1]][1])
				{
					$max_word = array(substr($text, 0, $position+1), $i);
				}
			}
			if(!$a[0]->no_current_states())
				$automats_are_empty = false;
		}
		$position++;
	}
	if($max_word == null)
		$max_word = array($text[0],-1);
	foreach($priorities as $a)
	{
		$a[0]->reset();
	}
	return $max_word;
}

function highlight($text)
{
	global $arr_reserved;
	$comments = comments_automat();
	$identifier = identifier_automat();
	$reserved = reserved_words_automat($arr_reserved);
	$punctuation = punctuation_automat();
	$long_punctuation = long_punctuation_automat();
	$whitespace = whitespace_automat();
	$string_literal = string_literal_automat();
	$number_literal = number_literal_automat();
	$float_number = float_number_literal_automat();

	$priorities = array(
		array($comments, 1),
		array($identifier, 1),
		array($reserved, 2),
		array($punctuation, 1),
		array($long_punctuation, 1),
		array($whitespace, 1),
		array($string_literal, 1),
		array($number_literal, 1),
		array($float_number, 1)
		);
	$colors = array(
		array($comments, "comment"),
		array($identifier, "identifier"),
		array($reserved, "reserved"),
		array($punctuation, "punctuation"),
		array($long_punctuation, "punctuation"),
		array($whitespace, "whitespace"),
		array($string_literal, "string_literal"),
		array($number_literal, "number_literal"),
		array($float_number, "float_number")
		);
	$position = 0;
	$result = "";
	while ($position<strlen($text)) 
	{
		$lexem = get_lexem(substr($text, $position, strlen($text)), $priorities);
		if($lexem[1]==-1)
		{
			$result .= wrap($lexem[0],"error");
		} 
		else 
		{
			$result .= wrap($lexem[0], $colors[$lexem[1]][1]);
		}
		$position += strlen($lexem[0]);
	}
	return $result;
}
function wrap($text, $class) 
{
	return "<span class = $class>" . htmlspecialchars($text) . "</span>";
}

$file = fopen(__FILE__, "rb");
$text = fread($file, filesize(__FILE__));
fclose($file);

$a =  comments_automat();
$a->go("#");
$a->go("a");
$a->go("d");
$a->go("\n");

?>

<!DOCTYPE html>
<html>
<head>
	<title></title>
	<style type="text/css">
		body{color: #ffffff;}
		.comment{color: #757154;}
		.identifier{color: #ffffff;}
		.reserved{color: #3da3ef;}
		.punctuation{color: #f9263e;}
		.string_literal{color: #e6db74;}
		.number_literal{color: #a6e22a;}
		.float_number{color: #a6e22a;}
		.error{color: red;}
	</style>
</head>
<body style = "background: #272822;">
<pre><?=highlight($text)?></pre>
</body>
</html>
