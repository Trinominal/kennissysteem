#!/usr/local/bin/php
<?php

error_reporting(E_ALL);
ini_set('display_errors', true);

include 'util.php';
include 'solver.php';
include 'reader.php';

function usage($path)
{
	echo "Usage: $path knowledge.xml [goal]";
	exit;
}

function main($argc, $argv)
{
	if ($argc < 2 || $argc > 4)
		usage($argv[0]);
	
	if ($argv[1] == '-v')
	{
		verbose(true);
		$argc--;
		array_shift($argv);
	}
	else
		verbose(false);

	$reader = new KnowledgeBaseReader;

	$knowledge = $reader->parse($argv[1]);

	$solver = new Solver;

	// Indien er nog een 2e argument is meegegeven, gebruik
	// dat als goal om af te leiden.
	if ($argc == 3)
	{
		$goal = new Goal;
		$goal->description = "Is {$argv[2]} waar?";
		$goal->proof = trim($argv[2]);

		$goals = array($goal);
	}
	// anders leid alle goals in de knowledge base af.
	else
	{
		$goals = $knowledge->goals;
	}

	proof($goals, $knowledge, $solver);
}

function proof($goals, $state, $solver)
{	
	foreach($goals as $goal)
		$state->goalStack->push($goal->proof);
	
	while (($question = $solver->solveAll($state)) instanceof AskedQuestion)
	{
		$answer = cli_ask($question);

		if ($answer instanceof Option)
			$state->apply($answer->consequences,
				Yes::because("User answered '{$answer->description}' to '{$question->description}'"));
	}
	
	// Print the results!
	foreach ($goals as $goal)
	{
		$result = $state->facts[$goal->proof];

		var_dump($result);

		printf("%s: %s\n",
			$goal->description,
			$result);
	}
}

/**
 * Stelt een vraag op de terminal, en blijf net zo lang wachten totdat
 * we een zinnig antwoord krijgen.
 * 
 * @return Option
 */
function cli_ask(Question $question, $skippable = false)
{
	echo $question->description . "\n";

	for ($i = 0; $i < count($question->options); ++$i)
		printf("%2d) %s\n", $i + 1, $question->options[$i]->description);
	
	if ($skippable)
		printf("%2d) weet ik niet\n", ++$i);
	
	do {
		$response = fgetc(STDIN);

		$choice = @intval(trim($response));

		if ($choice > 0 && $choice <= count($question->options))
			return $question->options[$choice - 1];
		
		if ($skippable && $choice == $i)
			return null;

	} while (true);
}

main($argc, $argv);