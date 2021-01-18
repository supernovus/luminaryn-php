#!/bin/env php
<?php
// Build script requires PHP 7.1 or higher.

// Constants

const SRC = './src/';
const RULES = SRC . 'rules/';
const CEXT = '.json';
const CMAIN = SRC . 'config' . CEXT;
const CTARG = SRC . 'targets' . CEXT;

const C_DDIR = 'output_path';
const C_DREPO = 'docker_name';
const C_DCMD = 'docker_cmd';
const C_PHPVER = 'php_versions';
const C_TAG_TMPL = 'tag_template';
const C_FILE_TMPL = 'file_template';
const C_BUILD_IMG = 'build_image';
const C_PUSH_IMG = 'push_image';
const C_PULL_PHP = 'pull_images';

const VAR_PHPVER = '{php_version}';
const VAR_TARGET = '{target}';

const DEFAULT_TAG_TMPL = VAR_PHPVER.'-'.VAR_TARGET;
const DEFAULT_FILE_TMPL = 'Dockerfile.'.DEFAULT_TAG_TMPL;
const DEFAULT_DOCKER_CMD = 'docker';

// Functions

function get_json (string $file): array
{
  if (file_exists($file) && is_readable($file))
  {
    $json = json_decode(file_get_contents($file), true);
    if (isset($json) && is_array($json) && count($json) > 0)
    { // It's at least an array with data, continue.
      return $json;
    }
    else
    { // Not valid.
      throw new Exception ("File '$file' does not return a valid JSON object.");
    }
  }
  else
  {
    throw new Exception("File '$file' does not exist or is not readable.");
  }
}

function get_rule (string $name): string
{
  $file = RULES . $name;
  if (file_exists($file) && is_readable($file))
  {
    return file_get_contents($file);
  }
  else
  {
    throw new Exception("File '$file' does not exist or is not readable.");
  } 
}

function get_conf (string $name, $default, $test)
{
  global $config;
  if (isset($config[$name]))
  {
    $val = $config[$name];
    if (isset($test) && is_callable($test))
    {
      if (!$test($val))
      {
        throw new Exception("Invalid '$name' value in '".CMAIN."'.");
      }
    }
    return $val;
  }
  elseif (isset($default))
  {
    return $default;
  }
  else
  {
    throw new Exception("Config key '$name' not set in '".CMAIN."'.");
  }
}

function get_target (string $name): array
{
  global $targets;
  if (isset($targets[$name]) && is_array($targets[$name]))
  {
    return $targets[$name];
  }
  else
  {
    throw new Exception("Target '$name' not set in '".CTARG."'.");
  }
}

function get_php_repo ($phpver): string
{
  global $php_vers;
  return $php_vers[$phpver]['repo'];
}

function tmpl (string $template, $phpver, string $target): string
{
  $repvals =
  [
    VAR_PHPVER => $phpver,
    VAR_TARGET => $target,
  ];
  $keys = array_keys($repvals);
  $vals = array_values($repvals);
  return str_replace($keys, $vals, $template);
}

function tag_name ($phpver, $target): string
{
  global $docker_repo, $tag_tmpl;
  return $docker_repo . ':' . tmpl($tag_tmpl, $phpver, $target);
}

function output_file ($phpver, $target): string
{
  global $docker_dir, $file_tmpl;
  return $docker_dir . '/' . tmpl($file_tmpl, $phpver, $target);
}

function docker ($cmdline)
{
  global $docker_cmd;
  return system("$docker_cmd $cmdline");
}

function build_dockerfile ($phpver, $target, $def=null)
{
  global $status, $build_img, $push_img;

  if (!isset($def))
  {
    $def = get_target($target);
  }
  $tag = tag_name($phpver, $target);
  if (isset($status[$tag]))
  { // It's already been processed likely in a 'from' call.
    return $status[$tag];
  }

  if (isset($def['from']))
  { // We have an explicit parent image.
    $from = tag_name($phpver, $def['from']);
    if (!isset($status[$from]))
    { // Hasn't been processed yet.
      build_dockerfile($phpver, $from);
    }

    if (!$status[$from])
    { // If the status is false, it means we're skipping it.
      $status[$tag] = false;
      return false;
    }
  }
  else
  { // Using the base PHP image.
    $from = get_php_repo($phpver);
  }

  $use = null;
  if (isset($def['if']) && is_array($def['if']))
  { // Conditional use statements.
    $matched = false;
    foreach ($def['if'] as $test)
    {
      if (is_array($test) && isset($test['use']) && is_array($test['use']))
      { // This is a valid test.
        if (isset($test['ver']) && $phpver == $test['ver'])
        {
          $matched = true;
          $use = $test['use'];
          break;
        }
      }
      else
      { // Report it and continue.
        error_log("Invalid if test: ".json_encode($test));
      }
    }
    if (!$matched)
    { // Didn't match, skip it.
      $status[$tag] = false;
      return false;
    }
  }
  elseif (isset($def['use']) && is_array($def['use']))
  { // Unconditional use statement.
    $use = $def['use'];
  }
  else
  { // Nothing to use?
    error_log("Invalid target definition, no 'use' found: ".json_encode($def));
  }

  $dockerfile = "FROM $from\n";
  foreach ($use as $file)
  {
    $rule = get_rule($file);
    $dockerfile .= $rule . "\n";
  }

  // If we made it here, we should have the text for a complete Dockerfile.
  $filename = output_file($phpver, $target);
  if (!file_put_contents($filename, $dockerfile))
  {
    throw new Exception("Could not write to '$filename'");
  }

  // Mark the dockerfile build as complete.
  $status[$tag] = true;

  if ($build_img)
  {
    docker("build -f $filename -t $tag .");
  }

  if ($push_img)
  {
    docker("push $tag");
  }

  return true;
}

// Global variables.

$config = get_json(CMAIN);
$targets = get_json(CTARG);

$is_bool = fn($v) => is_bool($v);
$is_string = fn($v) => is_string($v);

$docker_dir = get_conf(C_DDIR, null,
  fn($v) => (file_exists($v) && is_dir($v) && is_readable($v)));

$php_vers = get_conf(C_PHPVER, null, function ($v)
{
  if (is_array($v) && count($v) > 0)
  {
    foreach ($v as $k => $w)
    {
      if (!is_array($w) || !isset($w['repo']) || !is_string($w['repo']))
      {
        error_log("Invalid PHP version definition: ".json_encode($w));
        return false;
      }
    }
    return true;
  }
  error_log(C_PHPVER." must be an associative array.");
  return false;
});

$docker_repo = get_conf(C_DREPO, null, $is_string);
$docker_cmd  = get_conf(C_DCMD, DEFAULT_DOCKER_CMD, $is_string);
$tag_tmpl = get_conf(C_TAG_TMPL, DEFAULT_TAG_TMPL, $is_string);
$file_tmpl = get_conf(C_FILE_TMPL, DEFAULT_FILE_TMPL, $is_string);
$build_img = get_conf(C_BUILD_IMG, false, $is_bool);
$push_img = get_conf(C_PUSH_IMG, $build_img, $is_bool);
$pull_images = get_conf(C_PULL_PHP, $build_img, $is_bool);

// The main loop.

$status = [];
foreach ($php_vers as $phpver => $verdef)
{
  if ($pull_images)
  { // Pull the base PHP image.
    docker('pull '.$verdef['repo']);
  }

  foreach ($targets as $tname => $tdef)
  {
    echo "Building '$tname' for PHP '$phpver'...";
    $ok = build_dockerfile($phpver, $tname, $tdef);
    echo ($ok ? "done.\n" : "skipped.\n");
  }
}
echo "All built.\n";
