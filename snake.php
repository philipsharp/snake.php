<?php

/**
 * Snake
 *
 * @author Philip Sharp <philip@kerzap.com>
 */

$g = new SnakeGame();
$g->play();

/**
 * Game controller
 */
class SnakeGame {
    /**
     * @var SnakeBoard
     */
    protected $_board;
     
    /**
     * @var SnakePlayer
     */
    protected $_player;
    
    /**
     * Randomly-chosen size of the game board
     *
     * @var int
     */
    protected $_size;
    
    /**
     * Current fruit position
     * 
     * @var array (x,y)
     */
    protected $_fruit;
    
    /**
     * @var int
     */
    protected $_score;
    
    // Arrow key escape codes
    const UP = "\033[A";
    const DOWN = "\033[B";
    const RIGHT = "\033[C";
    const LEFT =  "\033[D";
    
    /**
     * Constructor
     *
     * Initialize the game, board, player
     */
    public function __construct(){
        $this->_size = rand(10,20);
        
        $this->_player = new SnakePlayer();
        $this->_board = new SnakeBoard($this->_size);
        
        $this->_score = 0;
    }
    
    /**
     * Play the game
     */
    public function play(){
        // position the player
        $startingPosition = $this->_getRandomPosition();
        $this->_player->addPosition($startingPosition);
        
        // set up the console
        $this->_board->setup();
        $this->_board->drawSnakeElement($startingPosition);
        $this->_board->drawScore($this->_score);
        $this->_placeFruit();
        
        // actually play the game
        $this->_loop();
    }
    
    /**
     * Find a new position for the fruit and draw it
     */
    protected function _placeFruit(){
        do{
            $this->_fruit = $this->_getRandomPosition();
        } while( $this->_player->isAtLocation($this->_fruit));
        
        $this->_board->drawFruit($this->_fruit);
    }
    
    /**
     * Main game loop
     *
     * Set up the TTY and listen for keys
     */
    protected function _loop(){
        
        // Save current TTY settings for later
        $oldStyle = shell_exec('stty -g');
        
        // Don't echo, allow special characters
        shell_exec('stty -echo -icanon');
        
        // this gets set to false if the game is over or the player exists
        $stillPlaying = true;
        
        do{
            $cmd = fread(STDIN,3);
            if ($cmd){
                switch ($cmd){
                    case self::UP:
                        $stillPlaying = $this->_movePlayer(0,-1);
                        break;
                    case self::DOWN:
                        $stillPlaying = $this->_movePlayer(0,1);
                        break;
                    case self::LEFT:
                        $stillPlaying = $this->_movePlayer(-1,0);
                        break;
                    case self::RIGHT:
                        $stillPlaying = $this->_movePlayer(1,0);
                        break;
                    case "\033"; // ESC
                    case 'q';
                        $stillPlaying = false;
                        break;
                }
            }
        } while($stillPlaying);
        
        // Restore TTY settings
        shell_exec('stty ' . $oldStyle);
    }
    
    /**
     * Main player action
     *
     * Attempts to move the player in the given direction
     *
     * Returns false when the game is over
     *
     * @param int $x Number of squares to move left (<0) or right (>0)
     * @param int $y Number of squares to move up (<0) or down (>0)
     * @return bool
     */
    protected function _movePlayer($x,$y){
        // Get current head and calculate next position
        $currentPosition = $this->_player->getHeadPosition();
        $newPosition = $currentPosition;
        $newPosition[0] += $x;
        $newPosition[1] += $y;
        
        // Can they move? (Is it the edge?)
        if ($this->_isValidMove($newPosition)){
            // Check for hitting self
            if ($this->_player->isAtLocation($newPosition)){
                echo 'GAME OVER' . PHP_EOL;
                return false;
            }
            // Check for hitting fruit, if so increment the score
            // and grow the snake
            elseif ($this->_atFruit($newPosition)){
                $this->_score++;
                $this->_board->drawScore($this->_score);
                $this->_player->addPosition($newPosition);
                $this->_board->drawSnakeElement($newPosition);
                $this->_placeFruit();
            }
            // Otherwise just move
            else {
                $lastPosition = $this->_player->removePosition();
                $this->_player->addPosition($newPosition);
                $this->_board->drawSnakeElement($newPosition);
                $this->_board->clearSquare($lastPosition);
            }
        }
        return true;
    }
    
    /**
     * Get a random position within the game board
     *
     * @return array (x,y)
     */
    protected function _getRandomPosition(){
        return array(rand(1,$this->_size),rand(1,$this->_size));
    }
    
    /**
     * Can the player move here?
     *
     * @return bool
     */
    protected function _isValidMove($position){
        if ($position[0] > 0 && $position[0] <= $this->_size &&
            $position[1] > 0 && $position[1] <= $this->_size){
            return true;
        }
        return false;
    }
    
    /**
     * Is the fruit here?
     *
     * @return bool
     */
    protected function _atFruit($position){
        return ($position[0] == $this->_fruit[0] &&
                $position[1] == $this->_fruit[1]);
    }
}

/**
 * Display handling
 */
class SnakeBoard {
    
    const BLANK = '·';
    const SNAKE = 'X';
    const FRUIT = 'O';
    
    /**
     * @var int
     */
    protected $_size;
    
    public function __construct($size){
        $this->_setSize($size);
    }
    
    /**
     * Do the initial setup
     */
    public function setup(){
        $this->_clearScreen();
        $this->_drawGrid();
        $this->_resetCursor();
    }
    
    /**
     * Draw a position as empty
     */
    public function clearSquare(array $point){
        $this->_moveCursor($point);
        echo self::BLANK;
        $this->_resetCursor();
    }
    
    /**
     * Draw a position with a snake segment
     */
    public function drawSnakeElement(array $point){
        $this->_moveCursor($point);
        echo $this->_ansi('33m') .  self::SNAKE . $this->_ansi('0m');
        $this->_resetCursor();
    }
    
    /**
     * Draw a position as the fruit
     */
    public function drawFruit(array $point){
        $this->_moveCursor($point);
        echo $this->_ansi('32m') .  self::FRUIT . $this->_ansi('0m');
        $this->_resetCursor();
    }
    
    /**
     * Draw the score
     *
     * Label is left-aligned, score is right-aligned
     */
    public function drawScore($score){
        $score = (int)$score;
        $this->_moveCursor(array(0,$this->_size+2));
        $pad = $this->_size + 2 - 7;
        echo 'Score: ' . sprintf("% {$pad}u", $score);
        $this->_resetCursor();
    }
    
    /**
     * Set the size of the grid
     */
    protected function _setSize($size){
        $size = (int)$size;
        if ($size <= 10 ){
            throw new RangeException('Size cannot be less than 10.');
        }
        elseif ($size > 20){
            throw new RangeException('Size cannot be greater than 20.');
        }
        
        $this->_size = $size;
    }
    
    /**
     * Clear the screen
     */
    protected function _clearScreen(){
        $this->_moveCursor(array(0,0));
        echo $this->_ansi('J');
    }
    
    /**
     * Draw the grid, with border
     */
    protected function _drawGrid(){
        echo str_repeat('-',$this->_size+2) . PHP_EOL;
        for ($i=0;$i<$this->_size;$i++){
            echo '|' . str_repeat(self::BLANK,$this->_size) . '|' . PHP_EOL;
        }
        echo str_repeat('-',$this->_size+2) . PHP_EOL;
    }
    
    /**
     * Position the cursor for writing
     */
    protected function _moveCursor(array $point){
        $x = (int)$point[0] + 1;
        $y = (int)$point[1] + 1;
        echo $this->_ansi("{$y};{$x}H");
    }
    
    /**
     * Position the cursur below the game
     */
    protected function _resetCursor(){
        $this->_moveCursor(array(0,$this->_size+3));
    }
    
    /**
     * Format command for ANSI escape sequence
     *
     * @return string
     */
    protected function _ansi($command){
        return "\033[{$command}";
    }
}

/**
 * A game player
 */
class SnakePlayer {
    /**
     * @var array
     */
    protected $_positions;
    
    public function __construct(){
        $this->_positions = array();
    }
    
    /**
     * Add a new segment at the player's head
     *
     * @param array
     */
    public function addPosition($point){
        array_unshift($this->_positions, $point);
    }
    
    /**
     * Remove the last segment of the player's tail
     *
     * @return array
     */
    public function removePosition(){
        return array_pop($this->_positions);
    }
    
    /**
     * Get the position of the player's head
     *
     * @return array
     */
    public function getHeadPosition(){
        return $this->_positions[0];
    }
    
    /**
     * Check if any part of the player is at the location
     *
     * @return bool
     */
    public function isAtLocation($point){
        foreach($this->_positions as $p){
            if ($p[0] == $point[0] &&
                $p[1] == $point[1]){
                return true;
            }
        }
        return false;
    }
}
