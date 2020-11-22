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
	// 0,
	// 101, // show next lines together
	108, // code comment
	201, // transfer player
	205, // set movement route
	230, // wait
	250, // sound
	// 356, // plugin command
];

$script = [];
$script[] = "label map_" . strtolower($map) . ":";

$calledVars = [];
$rpgmLabels = [];
$extraIndent = 0;

$vars = $switches = [];
foreach ($events as $ei => $event) {

	echo "EVENT: " . $event['name'] . " (" . $event['id'] . ")\n";
	// if ($event['name'] !== 'Picnic') continue;

	foreach ($event['pages'] as $pi => $page) {
		$rpgmLabels = [];

		// if ($event['name'] . '-' . $pi !== 'Picnic-2') continue;
		echo "\tPAGE: $pi\n";

$script[] = "";
$script[] = "label map" . $map . "_event{$event['id']}_page{$pi}:";
		foreach ($page['list'] as $li => $command) {
			if (in_array($command['code'], $ignore)) continue;

			$indent = str_repeat('    ', $extraIndent + $command['indent']);

			$params = $command['parameters'];
			echo "\t\t[". $command['indent'] . "] " . $command['code'] . ": ";

			switch ($command['code']) {
				case 0:
$script[] = "$indent    pass";
					break;

				case 356:
					echo "(plugin)";
$script[] = "$indent    pass";
					break;

				case 231: // Show picture
					echo 'picture: ' . $params[1];
$script[] = "$indent    scene " . strtolower($params[1]);
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
					$speaker = '';
					echo "(show lines:)";
					break;

				case 401: // Text line
					$message = $params[0];
					if (preg_match('#\\\n<(.+?)>#', $message, $match)) {
						$speaker = $match[1];
						if (preg_match('#^\\\N\[(\d+)\]$#', $speaker, $match2)) {
							$speaker = $names[$match2[1]] ?? '??';
						}
						elseif (preg_match('#^\\\V\[(\d+)\]$#', $speaker, $match2)) {
							$speaker = $vars[$match2[1]] ?? '??';
						}
						$message = str_replace($match[0], '', $message);
					}
					$message = preg_replace('#^\\\fi ?#', '{i}', $message);
					$message = preg_replace('#\\\C\[\d+\]#', '', $message);
					$message = preg_replace_callback('#\\\V\[(\d+)\]#', function($match) {
						return '[var' . $match[1] . ']';
					}, $message);
					$message = str_replace('%', '%%', $message);
					if ($speaker) {
						echo '"' . trim($speaker, '"') . '" "' . $message . '"';
$script[] = $indent . '    "' . trim($speaker, '"') . '" "' . addslashes($message) . '"';
					}
					else {
						echo '"' . $message . '"';
$script[] = $indent . '    "' . addslashes($message) . '"';
					}
					break;

				case 125: // Change Gold
					echo "(gold)";
					break;

				case 126: // Change Items
					echo "(items)";
					break;

				case 121: // Set switches
					foreach (range($params[0], $params[1]) as $n) {
						$calledVars[] = "switch$n";
						$switches[$n] = $params[2] === 0;
						echo "set switch$n = " . ($params[2] === 0 ? 'True' : 'False');
$script[] = "$indent    \$ switch$n = " . ($params[2] === 0 ? 'True' : 'False') . "";
					}
					break;

				case 122: // Set variable
					$op = ['=', '+=', '-=', '*=', '/=', '%='][$params[2]] ?? '?=';
					foreach (range($params[0], $params[1]) as $n) {
						$calledVars[] = "var$n";
						switch ($params[3]) {
							case 0: // Constant
								$value = is_int($params[4]) || is_float($params[4]) ? $params[4] : "'" . addslashes($params[4]) . "'";
								$vars[$n] = $params[4];
								echo "set var$n $op " . $value . ", ";
$script[] = "$indent    \$ var$n $op $value";
								break;

							case 1: // Variable
								$calledVars[] = "var{$params[4]}";
								$vars[$n] = $vars[$params[4]] ?? '??';
								echo "set var$n $op var" . $params[4] . ", ";
$script[] = "$indent    \$ var$n $op var" . $params[4] . "";
								break;

							case 2: // Random
								$vars[$n] = rand($params[4], $params[5]);
								echo "set var$n $op rand({$params[4]}, {$params[5]}), ";
$script[] = "$indent    \$ var$n $op renpy.random.Random().randint({$params[4]}, {$params[5]})";
								break;
						}
					}
					break;

				case 112: // Start loop
					echo "(start loop)";
// $script[] = "$indent    loop:";
$script[] = "$indent    if True:";
					break;

				case 113: // Start loop
					echo "(end loop)";
// $script[] = "$indent    endloop";
					break;

				case 111: // If
					echo "IF ";
					switch ($params[0]) {
						case 0: // Switch
							$calledVars[] = "switch{$params[1]}";
							echo "switch" . $params[1] . " == " . ($params[2] === 0 ? 'True' : 'False');
							break;

						case 1: // Variable
							$calledVars[] = "var{$params[1]}";
							if ($params[2] == 0) {
								$value2 = $params[3];
							}
							else {
								$calledVars[] = "var{$params[3]}";
								$value2 = "var{$params[3]}";
							}
							$op = ['==', '>=', '<=', '>', '<', '!='][$params[4]] ?? '?=';
							echo "var" . $params[1] . " $op " . $value2;
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
$script[] = "$indent    if True:";
// $script[] = "$indent        \"\"";
					break;

				case 411: // Else
					echo 'ELSE:';
$script[] = "$indent    else:";
					break;

				case 412: // End if
					echo 'ENDIF';
					break;

				case 102: // Menu/branches
					echo 'MENU: "' . strtoupper(implode('", "', $params[0])) . '"';
$script[] = "$indent    menu:";
$extraIndent++;
					break;

				case 402: // Branch start
					echo '(BRANCH: ' . strtoupper($params[1]) . ':)';
$script[] = $indent . '    "' . addslashes($params[1]) . '":';
					break;

				case 404: // Branch end
					echo 'ENDMENU';
$extraIndent--;
					break;

				case 118: // Define label
					// $rpgmLabels[$params[0]] = $label = 'label_' . $params[0] . '_' . rand();
					$label = "m{$map}_e{$ei}_p{$pi}_{$params[0]}";
					echo 'label: ' . $params[0];
$script[] = "";
$script[] = "$indent    label $label:";
$script[] = "$indent        pass";
					break;

				case 119: // Jump to label
					// $label = $rpgmLabels[$params[0]] ?? 'label_UNKNOWN_' . $params[0];
					$label = "m{$map}_e{$ei}_p{$pi}_{$params[0]}";
					echo 'jump: ' . $params[0];
$script[] = "$indent    jump $label";
					break;
			}
			echo "\n";
		}
	}
}

$calledVars = array_values(array_unique($calledVars));
$varsScript = array_map(function($var) {
	return "define $var = 0";
}, $calledVars);
array_unshift($script, implode("\n", $varsScript) . "\n\n");

echo "\n\n\n\n\n\n";
// print_r($calledVars);

file_put_contents($file = __DIR__ . '/game/' . strtolower($map) . '.rpy', implode("\n", $script));
// readfile($file);
// print_r($script);
