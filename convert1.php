<?php

ini_set('html_errors', '0');

function machinename($label) {
	return trim(preg_replace('#[^a-z0-9_]#', '_', strtolower($label)), '_') . '_' . rand();
}

$names = array_map(function($info) {
	return $info['name'];
}, array_filter(json_decode(file_get_contents(__DIR__ . '/game/data/Actors.json'), true)));
$names[1] = 'MC';

$map = $_GET['map'] ?? 'Map001';

/**
?>
<script src="convert.js"></script>
<script>
const map = new Map(<?= file_get_contents(__DIR__ . '/game/data/' . $map . '.json') ?>);
</script>
<?php

exit;
/**/



header('Content-type: text/plain; charset=utf-8');

$mapData = json_decode(file_get_contents(__DIR__ . '/game/data/' . $map . '.json'), true);
$events = array_filter($mapData['events']);
unset($mapData);

$ignore = [
	0,
	101, // show next lines together
	108, // code comment
	201, // transfer player
	205, // set movement route
	230, // wait
	250, // sound
	356, // plugin command
];

$menus = [];

$script = [];

$script[] = "label map_" . strtolower($map) . ":";

$vars = [];
foreach ($events as $ei => $event) {

	echo "EVENT: " . $event['name'] . " (" . $event['id'] . ")\n";
	// if ($event['name'] !== 'Picnic') continue;

	foreach ($event['pages'] as $pi => $page) {

		if ($event['name'] . '-' . $pi !== 'Picnic-2') continue;
		echo "\tPAGE: $pi\n";

$script[] = "";
$script[] = "label event{$event['id']}_page{$pi}:";
		foreach ($page['list'] as $li => $command) {
			if (in_array($command['code'], $ignore)) continue;

			$params = $command['parameters'];
			echo "\t\t[". $command['indent'] . "] " . $command['code'] . ": ";

			switch ($command['code']) {
				case 231: // Show picture
					echo 'picture: ' . $params[1];
$script[] = "    scene " . strtolower($params[1]);
					break;

				case 232: // Move picture
					echo "(move pic)";
					break;

				case 235: // Hide picture
					echo "(hide pic)";
					break;

				case 221: // Fade
				case 222: // Fade
				case 223: // Fade
					echo "(fade)";
					break;

				case 101: // Show following text lines
					echo "(show lines:)";
					break;

				case 401: // Text line
					$name = '';
					$message = $params[0];
					if (preg_match('#\\\n<(.+?)>#', $message, $match)) {
						$name = $match[1];
						if (preg_match('#^\\\N\[(\d+)\]$#', $name, $match2)) {
							$name = $names[$match2[1]] ?? '??';
						}
						elseif (preg_match('#^\\\V\[(\d+)\]$#', $name, $match2)) {
							$name = $vars[$match2[1]] ?? '??';
						}
						$message = str_replace($match[0], '', $message);
					}
					$message = preg_replace('#^\\\fi ?#', '{i}', $message);
					if ($name) {
						echo '"' . trim($name, '"') . '" "' . $message . '"';
$script[] = '    "' . trim($name, '"') . '" "' . addslashes($message) . '"';
					}
					else {
						echo '"' . $message . '"';
$script[] = '    "' . addslashes($message) . '"';
					}
					break;

				case 125: // Change Gold
					echo "(gold)";
					break;

				case 126: // Change Items
					echo "(items)";
					break;

				case 121: // Set switches
					echo 'set switch ' . implode(', ', range($params[0], $params[1])) . ' = ' . ($params[2] === 0 ? 'True' : 'False');
					break;

				case 122: // Set variable
					foreach ($r = range($params[0], $params[1]) as $var) {
						$vars[$var] = $params[4];
					}
					echo "set var " . implode(', ', $r) . " = " . $params[4];
					break;

				case 111: // If
					echo "IF ";
					switch ($params[0]) {
						case 0: // Switch
							echo "switch " . $params[1] . " == " . ($params[2] === 0 ? 'True' : 'False');
							break;

						case 1: // Variable
							$value2 = $params[2] == 0 ? $params[3] : "var $params[3]";
							$op = ['==', '>=', '<=', '>', '<', '!='][$params[4]] ?? '?=';
							echo "var " . $params[1] . " $op " . $value2;
							break;

						case 4: // Actor
							echo "actor " . $params[1];
							break;

						case 6: // Character
							echo "character " . $params[1];
							break;

						case 7: // Gold
							$op = ['>=', '<=', '<'][$params[2]] ?? '?=';
							echo "gold $op " . $params[1];
							break;

						case 8: // Item
							echo "has item " . $params[1];
							break;

						default:
							echo "?";
					}
					echo ":";
					break;

				case 411: // Else
					echo 'ELSE:';
					break;

				case 412: // End if
					echo 'ENDIF';
					break;

				case 102: // Menu/branches
					echo 'MENU: "' . strtoupper(implode('", "', $params[0])) . '"';
$menus[$menu = rand()] = [];
$script[] = "";
$script[] = "    # menu $menu";
$script[] = "    menu:";
foreach ($params[0] as $o => $option) {
	$randlabel = machinename($option);
	$script[] = '        "' . addslashes($option) . '":';
	$script[] = '            jump ' . $randlabel;
	$menus[$menu][] = $randlabel;
}
$script[] = "    # endmenu\n";
					break;

				case 402: // Branch start
					echo '(BRANCH: ' . strtoupper($params[1]) . ':)';
if (trim(end($script)) != "# endmenu") {
	$script[] = "    jump endmenu_" . array_key_last($menus);
}
$script[] = "";
$script[] = "label " . array_shift($menus[array_key_last($menus)]) . ":";
					break;

				case 404: // Branch end
					echo 'ENDMENU';
$label = array_key_last($menus);
$script[] = "    jump endmenu_$label";
$script[] = "";
$script[] = "label endmenu_$label:";
					break;

				case 118: // Define label
					echo 'label: ' . $params[0];
$script[] = "";
$script[] = "label label_" . $params[0] . ":";
					break;

				case 119: // Jump to label
					echo 'jump: ' . $params[0];
$script[] = "    jump label_" . $params[0];
					break;
			}
			echo "\n";
		}
	}
}

echo "\n\n\n\n\n\n";

// print_r($script);
file_put_contents($file = __DIR__ . '/game/' . strtolower($map) . '.rpy', implode("\n", $script));
readfile($file);
