<?php
/**
 * Plugin Name: Stack Trace Logger
 */

/**
 * 
 * Stack Trace Logger
 * 
 * Uses:
 * 
 * * Log to file 
 * ---------------------
 * logStackTrace('Debugging REST API issue');
 * 
 * Print to screen   
 * ---------------------
 * printStackTrace('Current execution path'); 
 * 
 * Get as string 
 * ----------------------
 * $trace = getStackTrace('Custom debug point'); 
 * error_log($trace); 
 * 
 * Direct class usage 
 * ----------------------
 * StackTraceLogger::log('Custom message'); 
 * StackTraceLogger::printTrace('Debug point');
 * 
 */
class StackTraceLogger {
	
	private static $logFile  = 'stacktrace.log';
	private static $maxDepth = 15;
	
	/**
	 * Set custom log file path
	 */
	public static function setLogFile( $filePath ) {
		self::$logFile = $filePath;
	}
	
	/**
	 * Set maximum stack trace depth
	 */
	public static function setMaxDepth( $depth ) {
		self::$maxDepth = $depth;
	}
	
	/**
	 * Log stack trace with hook name detection
	 * 
	 * @param string $message Optional custom message
	 * @param int    $skipFrames Number of frames to skip
	 */
	public static function log( $message = '', $skipFrames = 1 ) {
		$trace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, self::$maxDepth + $skipFrames );
		
		// Remove the frames we want to skip
		for ( $i = 0; $i < $skipFrames; $i++ ) {
			array_shift( $trace );
		}
		
		$logEntry = self::formatStackTrace( $message, $trace );
		self::writeToLog( $logEntry );
		
		return $logEntry;
	}
	
	/**
	 * Format the complete stack trace
	 */
	private static function formatStackTrace( $message, $trace ) {
		$timestamp = date( 'Y-m-d H:i:s' );
		$separator = str_repeat( '=', 80 );
		
		$logEntry  = "\n{$separator}\n";
		$logEntry .= "STACK TRACE - {$timestamp}\n";
		
		if ( $message ) {
			$logEntry .= "MESSAGE: {$message}\n";
		}
		
		$logEntry .= "{$separator}\n";
		
		foreach ( $trace as $index => $frame ) {
			$logEntry .= self::formatStackFrame( $index, $frame );
		}
		
		$logEntry .= "{$separator}\n";
		
		return $logEntry;
	}
	
	/**
	 * Format individual stack frame with hook name detection
	 */
	private static function formatStackFrame( $index, $frame ) {
		$file     = isset( $frame['file'] ) ? $frame['file'] : 'Unknown file';
		$line     = isset( $frame['line'] ) ? $frame['line'] : 'Unknown line';
		$function = isset( $frame['function'] ) ? $frame['function'] : 'Unknown function';
		$class    = isset( $frame['class'] ) ? $frame['class'] : '';
		$type     = isset( $frame['type'] ) ? $frame['type'] : '';
		
		// Build function call string
		$functionCall = $class ? "{$class}{$type}{$function}" : $function;
		
		// Extract hook name for WordPress functions
		$hookName = self::extractHookName( $frame );
		if ( $hookName ) {
			$functionCall .= " [HOOK: '{$hookName}']";
		}
		
		// Format the stack frame line
		$frameInfo = sprintf(
			"#%d %s called at [%s:%s]\n",
			$index,
			$functionCall,
			$file,
			$line
		);
		
		return $frameInfo;
	}
	
	/**
	 * Extract hook name from WordPress hook functions
	 */
	private static function extractHookName( $frame ) {
		if ( ! isset( $frame['function'] ) || ! isset( $frame['args'] ) ) {
			return null;
		}
		
		$function        = $frame['function'];
		$wpHookFunctions = array(
			'do_action',
			'apply_filters', 
			'do_action_ref_array',
			'apply_filters_ref_array',
		);
		
		// Check if this is a WordPress hook function
		if ( in_array( $function, $wpHookFunctions ) ) {
			// The first argument is always the hook name
			if ( isset( $frame['args'][0] ) && is_string( $frame['args'][0] ) ) {
				return $frame['args'][0];
			}
		}
		
		return null;
	}
	
	/**
	 * Write log entry to file
	 */
	private static function writeToLog( $logEntry ) {
		$logDir = dirname( self::$logFile );
		if ( ! is_dir( $logDir ) ) {
			mkdir( $logDir, 0755, true );
		}
		
		file_put_contents( self::$logFile, $logEntry, FILE_APPEND | LOCK_EX );
	}
	
	/**
	 * Clear log file
	 */
	public static function clearLog() {
		if ( file_exists( self::$logFile ) ) {
			file_put_contents( self::$logFile, '' );
		}
	}
	
	/**
	 * Get recent log entries
	 */
	public static function getRecentLogs( $lines = 50 ) {
		if ( ! file_exists( self::$logFile ) ) {
			return 'Log file not found.';
		}
		
		$file       = file( self::$logFile );
		$totalLines = count( $file );
		$startLine  = max( 0, $totalLines - $lines );
		
		return implode( '', array_slice( $file, $startLine ) );
	}
	
	/**
	 * Print stack trace to screen (for debugging)
	 */
	public static function printTrace( $message = '', $skipFrames = 1 ) {
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, self::$maxDepth + $skipFrames );
		
		// Remove the frames we want to skip
		for ( $i = 0; $i < $skipFrames; $i++ ) {
			array_shift( $trace );
		}
		
		echo "\n" . str_repeat( '=', 50 ) . "\n";
		echo 'STACK TRACE';
		if ( $message ) {
			echo " - {$message}";
		}
		echo "\n" . str_repeat( '=', 50 ) . "\n";
		
		foreach ( $trace as $index => $frame ) {
			$file     = isset( $frame['file'] ) ? $frame['file'] : 'Unknown file';
			$line     = isset( $frame['line'] ) ? $frame['line'] : 'Unknown line';
			$function = isset( $frame['function'] ) ? $frame['function'] : 'Unknown function';
			$class    = isset( $frame['class'] ) ? $frame['class'] : '';
			$type     = isset( $frame['type'] ) ? $frame['type'] : '';
			
			$functionCall = $class ? "{$class}{$type}{$function}" : $function;
			
			// Get original frame with args for hook detection
			$fullFrame = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, self::$maxDepth + $skipFrames )[ $index + $skipFrames ];
			$hookName  = self::extractHookName( $fullFrame );
			if ( $hookName ) {
				$functionCall .= " [HOOK: '{$hookName}']";
			}
			
			printf( "#%d %s called at [%s:%s]\n", $index, $functionCall, $file, $line );
		}
		
		echo str_repeat( '=', 50 ) . "\n\n";
	}
}

/**
 * Quick helper function to log stack trace
 */
function logStackTrace( $message = '' ) {
	StackTraceLogger::log( $message, 2 );
}

/**
 * Quick helper function to print stack trace to screen
 */
function printStackTrace( $message = '' ) {
	StackTraceLogger::printTrace( $message, 2 );
}

/**
 * Get formatted stack trace as string
 */
function getStackTrace( $message = '', $skipFrames = 1 ) {
	$trace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 15 + $skipFrames );
	
	// Remove the frames we want to skip
	for ( $i = 0; $i < $skipFrames + 1; $i++ ) {
		array_shift( $trace );
	}
	
	$output = "\nSTACK TRACE";
	if ( $message ) {
		$output .= " - {$message}";
	}
	$output .= "\n" . str_repeat( '-', 40 ) . "\n";
	
	foreach ( $trace as $index => $frame ) {
		$file     = isset( $frame['file'] ) ? $frame['file'] : 'Unknown file';
		$line     = isset( $frame['line'] ) ? $frame['line'] : 'Unknown line';
		$function = isset( $frame['function'] ) ? $frame['function'] : 'Unknown function';
		$class    = isset( $frame['class'] ) ? $frame['class'] : '';
		$type     = isset( $frame['type'] ) ? $frame['type'] : '';
		
		$functionCall = $class ? "{$class}{$type}{$function}" : $function;
		
		// Extract hook name
		$hookName = null;
		if ( isset( $frame['function'] ) && isset( $frame['args'] ) ) {
			$wpHookFunctions = array( 'do_action', 'apply_filters', 'do_action_ref_array', 'apply_filters_ref_array' );
			if ( in_array( $frame['function'], $wpHookFunctions ) && isset( $frame['args'][0] ) && is_string( $frame['args'][0] ) ) {
				$hookName = $frame['args'][0];
			}
		}
		
		if ( $hookName ) {
			$functionCall .= " [HOOK: '{$hookName}']";
		}
		
		$output .= sprintf( "#%d %s called at [%s:%s]\n", $index, $functionCall, $file, $line );
	}
	
	return $output;
}
