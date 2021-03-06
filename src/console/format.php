<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group console
 */
function phutil_console_format($format /* ... */) {
  $args = func_get_args();
  return call_user_func_array(
    array('PhutilConsoleFormatter', 'formatString'),
    $args);
}


/**
 * @group console
 */
function phutil_console_confirm($prompt, $default_no = true) {
  $prompt_options = $default_no ? '[y/N]' : '[Y/n]';

  do {
    $response = phutil_console_prompt($prompt.' '.$prompt_options);
    $c = trim(strtolower($response));
  } while ($c != 'y' && $c != 'n' && $c != '');
  echo "\n";

  if ($default_no) {
    return ($c == 'y');
  } else {
    return ($c != 'n');
  }
}


/**
 * @group console
 */
function phutil_console_prompt($prompt, $history = '') {

  echo "\n\n";
  $prompt = phutil_console_wrap($prompt.' ', 4);

  try {
    phutil_console_require_tty();
  } catch (PhutilConsoleStdinNotInteractiveException $ex) {
    // Throw after echoing the prompt so the user has some idea what happened.
    echo $prompt;
    throw $ex;
  }

  if ($history == '' || !shell_exec('echo $BASH 2> /dev/null')) {
    echo $prompt;
    $response = fgets(STDIN);

  } else {
    // There's around 0% chance that readline() is available directly in PHP.
    // execx() doesn't work with input, phutil_passthru() doesn't return output.
    $response = shell_exec(csprintf(
      'history -r %s 2> /dev/null;'.
      ' read -e -p %s;'.
      ' echo "$REPLY";'.
      ' history -s "$REPLY" 2> /dev/null;'.
      ' history -w %s 2> /dev/null',
      $history,
      $prompt,
      $history));
  }

  return rtrim($response, "\r\n");
}


/**
 * Soft wrap text for display on a console, respecting UTF8 character boundaries
 * and ANSI color escape sequences.
 *
 * @param   string  Text to wrap.
 * @param   int     Optional indent level.
 * @return  string  Wrapped text.
 *
 * @group console
 */
function phutil_console_wrap($text, $indent = 0) {
  $lines = array();

  $width = (78 - $indent);
  $esc = chr(27);

  $break_pos = null;
  $len_after_break = 0;
  $line_len = 0;

  $line = array();
  $lines = array();

  $vector = phutil_utf8v($text);
  $vector_len = count($vector);
  for ($ii = 0; $ii < $vector_len; $ii++) {
    $chr = $vector[$ii];

    // If this is an ANSI escape sequence for a color code, just consume it
    // without counting it toward the character limit. This prevents lines
    // with bold/color on them from wrapping too early.
    if ($chr == $esc) {
      for ($ii; $ii < $vector_len; $ii++) {
        $line[] = $vector[$ii];
        if ($vector[$ii] == 'm') {
          break;
        }
      }
      continue;
    }

    $line[] = $chr;

    ++$line_len;
    ++$len_after_break;

    if ($line_len > $width) {
      if ($break_pos !== null) {
        $slice = array_slice($line, 0, $break_pos);
        while (count($slice) && end($slice) == ' ') {
          array_pop($slice);
        }
        $slice[] = "\n";
        $lines[] = $slice;
        $line = array_slice($line, $break_pos);

        $line_len = $len_after_break;
        $len_after_break = 0;
        $break_pos = 0;
      }
    }

    if ($chr == " ") {
      $break_pos = count($line);
      $len_after_break = 0;
    }

    if ($chr == "\n") {
      $lines[] = $line;
      $line = array();

      $len_after_break = 0;
      $line_len = 0;
      $break_pos = null;
    }
  }

  if ($line) {
    if ($line) {
      $lines[] = $line;
    }
  }

  $pre = null;
  if ($indent) {
    $pre = str_repeat(' ', $indent);
  }

  foreach ($lines as $idx => $line) {
    $lines[$idx] = $pre.implode('', $line);
  }

  return implode('', $lines);
}


/**
 * @group console
 */
function phutil_console_require_tty() {
  if (function_exists('posix_isatty') && !posix_isatty(STDIN)) {
    throw new PhutilConsoleStdinNotInteractiveException();
  }
}
