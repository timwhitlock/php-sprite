<?php
/**
 * Sprite generator
 * 
 * @author Tim Whitlock
 * @license MIT
 */

require __DIR__.'/libs/php-cli/cli.php';

// dependency checks
if( ! function_exists('imagecreatetruecolor') ){
    cli::death('PHP GD module required');
}

cli::register_arg( 'd', 'dir',    'Directory containing images, defaults to cwd', false );
cli::register_arg( 'w', 'width',  'Minimum width of each cell, defaults to width of image + padding', false );
cli::register_arg( '',  'height', 'Minimum height of each cell, defaults to height of image + padding', false );
cli::register_arg( 'p', 'padding','Minimum distance between items, defaults to 1', false );
cli::register_arg( 'z', 'horiz',  'Whether to lay out horizontally, defaults to vertical', false );
cli::register_arg( 'n', 'name',   'CSS class prefix and file name, defaults to "sprite"', false );
cli::register_arg( 'c', 'colour', 'Opaque background color as hex, deaults to transparent', false );
cli::register_arg( '',  'wrap',   'Wrap at this many rows (horiz) or columns (vert)', false );
cli::register_arg( 's', 'scale',  'Scaling of final images, defaults to 1', false );
cli::register_arg( 'r', 'relative', 'Whether to use relative (%) image positions', false );
cli::validate_args();


$dir = rtrim( cli::arg('d'), "\n/") or $dir = '.';
if( ! is_readable($dir) || ! is_dir($dir) ){
    throw new Exception('Directory unreadable, try with -d open_basedir='.ini_get('open_basedir').':'.$dir);
}

$prefix = cli::arg('n','sprite');
$target = $dir.'/'.$prefix.'.png';

// get all image references
$files = array();
$rsc = opendir($dir);
while( $f = readdir($rsc) ){
    if( preg_match('/\.(png|jpe?g|gif)$/i', $f, $r ) ){
        $f = $dir.'/'.$f;
        if( $f === $target ){
            continue; // <- this is a previous exported sprite
        }
        $files[] = $f;
    }
}
closedir($rsc);

if( ! $files ){
    cli::stderr("no images files found\n");
    exit(1);
}

// configure sprite from command line flags

$Sprite = new CssSprite( cli::arg('width'), cli::arg('height'), cli::arg('horiz') );

$scale = cli::arg('s') and
$Sprite->set_scale( $scale );

$colour = cli::arg('c') and
$Sprite->set_colour( $colour );

$relative = cli::arg('r') and
$Sprite->use_relative($relative);

$wrap = cli::arg('wrap') and
$Sprite->wrap_at( $wrap );

$padding = cli::arg('padding') and
$Sprite->pad($padding);


// add all found files
foreach( $files as $path ){
    $Sprite->add_file( $path );
}

// export image to file
$png = $Sprite->compile();
imagepng( $png, $target );


// dump css, and tell me where the file is
echo "/* sprite saved to ",$target," */\n";
foreach( $Sprite->get_css($prefix) as $line ){
    echo $line,"\n";
}



//
class CssSprite {
    
    // config
    private $horiz;
    private $padding = 1;
    private $minwidth;
    private $minheight;
    private $wrapnum;
    private $_scale;
    private $colour = array( 0xFF, 0xFF, 0xFF, 127 );
    private $relative = false;
    
    // grid [ [ { x:, y: }, {} ], [ {} ] ]
    private $x = 0;
    private $y = 0;
    private $rows = array();
    private $r = 0;
    private $c = 0;
    
    // max bounds
    private $width = 0;
    private $height = 0;
    
    
    
    public function __construct( $minwidth = 0, $minheight = 0, $horiz = false ){
        $this->minwidth = (int) $minwidth;
        $this->minheight = (int) $minheight;
        $this->horiz = (bool) $horiz;
    }   
    
    
    public function add_file( $path ){
        $inf = getimagesize( $path );
        if( ! $inf || ! $inf[2] ){
            throw new Exception('Invalid image file '.$path);
        }
        list( $width, $height, $type ) = $inf;
        $name = preg_replace('/\.(png|jpe?g|gif)$/i','',basename($path) );
        // register this cell at current point the grid
        $this->rows[$this->r][$this->c] = array (
            'w' => $width,
            'h' => $height,
            't' => $type,
            'x' => $this->x,
            'y' => $this->y,
            'p' => $path,
            'n' => $name,
        );
        // increase bounds by minimum amount
        $this->width  = max( $this->width, $this->x + $width );
        $this->height = max( $this->height, $this->y + $height );
        // set grid space for this item
        $width += $this->padding;
        $width = $this->minwidth ? max( $this->minwidth, $width ) : $width;
        $height += $this->padding; 
        $height = $this->minheight ? max( $this->minheight, $height ) : $height;
        // move grid position ready for next item
        if( $this->horiz ){
            // wrap to next row?
            if( ++$this->c === $this->wrapnum ){
                $this->c = $this->x = 0;
                $this->r++;
                $this->y+= $height;
            }
            // no, increment to next column
            else {
                $this->x += $width;
            }
        }
        // wrap to top of next column?
        else if( ++$this->r === $this->wrapnum ){
            $this->c++;
            $this->x += $width;
            $this->r = $this->y = 0;
        }
        // no, increment to next row
        else {
            $this->y += $height;
        }
        return $this;
    }



    /**
     * 
     */
    public function pad( $px ){
        $this->padding = (int) $px;
    }
    
    
    /**
     * 
     */
    public function wrap_at( $col_or_row = null ){
        $was = $this->wrapnum;
        if( isset($col_or_row) ){
            $this->wrapnum = (int) $col_or_row;
        }
        return $was;
    }
    
    
    /**
     * 
     */
    public function use_relative( $bool = null ){
        $was = $this->relative;
        if( isset($bool) ){
            if( $bool ){
                if( ! $this->minwidth && ( $this->horiz || $this->wrapnum ) ){
                    throw new Exception('relative horizontal offsets require width setting');
                }
                if( ! $this->minheight && ( ! $this->horiz || $this->wrapnum ) ){
                    throw new Exception('relative vertical offsets require height setting');
                }
            }
            $this->relative = (bool) $bool;
        }
        return $was;
    }
    
    
    /**
     * 
     */
    public function set_scale( $scale ){
        $this->_scale = 1;
        if( $scale && '1' !== $scale ){
            $scale = floatval($scale) and
            $this->_scale = $scale;
        }
        // ensure padding is not scaled
        $this->padding = (int) ceil( $this->padding / $this->_scale );
        return $this->_scale;
    }

    
    
    /**
     * 
     */
    private function scale( $n ){
        if( ! $n ){
            return 0;
        }
        if( $this->_scale && 1 !== $this->_scale ){
            $n = (int) ceil( $this->_scale * $n );
        }
        return $n;
    }    


    
    /**
     * 
     */
    public function get_css( $prefix = 'sprite' ){
        $w = $this->minwidth  ? $this->scale($this->minwidth)  : 0;
        $h = $this->minheight ? $this->scale($this->minheight) : 0;
        if( $this->relative ){
            // background %age subtracts visible area from 100% so be sure to set --width and --height
            $ww = $this->scale( $this->width - $w );            
            $hh = $this->scale( $this->height - $h );            
        }
        $lines[] = sprintf(
            '.%s { background: url(%1$s.png) no-repeat; display: inline-block; min-width: %upx; min-height: %upx; }', 
            $prefix, $w, $h
        );
        foreach( $this->rows as $row ){
            foreach( $row as $cell ){
                extract( $cell );
                $x = $this->scale($x);
                $y = $this->scale($y);
                if( $this->relative ){
                    $x = $x ? ( $x === $ww ? 'right'  : sprintf( '%f%%', 100 * $x / $ww ) ) : '0';
                    $y = $y ? ( $y === $hh ? 'bottom' : sprintf( '%f%%', 100 * $y / $hh ) ) : '0';
                }
                else {
                    $x = $x ? '-'.$x.'px' : '0';
                    $y = $y ? '-'.$y.'px' : '0';
                }
                $lines[] = sprintf( '.%s-%s { background-position: %s %s; }', $prefix, $n, $x, $y);
            }
        }
        return $lines;
    }    
    
    
    /**
     * @todo handle alpha RRGGBBAA
     */
    public function set_colour( $colour ){
        if( ! is_array($colour) ){
            $n = intval( trim($colour,' #'), 16 );
            $colour = array( $n & 0xFF );
            array_unshift( $colour, ($n>>=8) & 0xFF );
            array_unshift( $colour, ($n>>=8) & 0xFF );
        }
        $this->colour = $colour + array(127,127,127,0);
    }    
    
    
    
    /**
     * Build final sprite image
     */
    public function compile(){
        $w = $this->scale( $this->width );
        $h = $this->scale( $this->height );
        // create transparent canvas to start
        $sprite = imagecreatetruecolor( $w, $h );
        imagesavealpha( $sprite, true );
        imagefill( $sprite, 0, 0, imagecolorallocatealpha( $sprite, $this->colour[0], $this->colour[1], $this->colour[2], $this->colour[3] ) );
        // superimpose all image files
        foreach( $this->rows as $row ){
            foreach( $row as $cell ){
                extract( $cell, EXTR_PREFIX_ALL, 'src' );
                extract( $cell, EXTR_PREFIX_ALL, 'dst' );
                $gd = imagecreatefromstring( file_get_contents($src_p) );
                $dst_x = $this->scale($dst_x);
                $dst_y = $this->scale($dst_y);
                $dst_w = $this->scale($dst_w);
                $dst_h = $this->scale($dst_h);
                if( ! imagecopyresampled( $sprite, $gd, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $src_w, $src_h ) ){
                    throw new Exception('Failed to composite '.$p);
                }
            }
        }
        return $sprite;
    }
    
    
}









