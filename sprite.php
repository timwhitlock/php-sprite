<?php
/**
 * Sprite generator
 * 
 * @author Tim Whitlock
 * @license MIT
 */

require __DIR__.'/libs/php-cli/cli.php';

cli::register_arg( 'd', 'dir',    'Directory containing images', true );
cli::register_arg( 'w', 'width',  'Minimum width of each cell, defaults to width of image + 1', false );
cli::register_arg( '',  'height', 'Minimum height of each cell, defaults to height of image + 1', false );
cli::register_arg( 'x', 'horiz',  'Whether to lay out horizontally, defaults to vertical', false );
cli::register_arg( 'p', 'prefix', 'CSS class prefix, defaults to "sprite"', false );
//cli::register_arg( 'w', 'wrap',   'Maximum no. of rows or columns', false );
cli::register_arg( 's', 'scale',   'Scaling of final images, defaults to 1', false );
cli::validate_args();


$dir = cli::arg('d');
if( ! is_readable($dir) || ! is_dir($dir) ){
    throw new Exception('Directory unreadable, try with -d open_basedir='.ini_get('open_basedir').':'.$dir);
}

$prefix = cli::arg('p','sprite');
$target = rtrim($dir," \n/").'/'.$prefix.'.png';

// get all image references
$files = array();
$rsc = opendir($dir);
while( $f = readdir($rsc) ){
    if( preg_match('/\.(png|jpe?g|gif)$/i', $f, $r ) ){
        $f = $dir.'/'.$f;
        if( $f === $target ){
            continue; // <- this is our export
        }
        $files[] = $f;
    }
}
closedir($rsc);

if( ! $files ){
    cli::stderr("no images files found\n");
    exit(1);
}

$Sprite = new CssSprite( cli::arg('w'), cli::arg('h'), cli::arg('x'), cli::arg('w') );
foreach( $files as $path ){
    $Sprite->add_file( $path );
}

$scale = cli::arg('s') and
$Sprite->set_scale( $scale );


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
    private $minwidth;
    private $minheight;
    private $wrapnum;
    private $scale;
    
    // grid [ [ { x:, y: }, {} ], [ {} ] ]
    private $x = 0;
    private $y = 0;
    private $rows = array();
    private $r = 0;
    
    // max bounds
    private $width = 0;
    private $height = 0;
    
    
    
    public function __construct( $minwidth = 0, $minheight = 0, $horiz = false, $wrapnum = 0 ){
        $this->minwidth = (int) $minwidth;
        $this->minheight = (int) $minheight;
        $this->horiz = (bool) $horiz;
        $this->wrapnum = (int) $wrapnum;
    }   
    
    
    public function add_file( $path ){
        $inf = getimagesize( $path );
        if( ! $inf || ! $inf[2] ){
            throw new Exception('Invalid image file '.$path);
        }
        list( $width, $height, $type ) = $inf;
        $name = preg_replace('/\.(png|jpe?g|gif)$/i','',basename($path) );
        // register this cell at current point the grid
        $this->rows[$this->r][] = array (
            'w' => $width,
            'h' => $height,
            't' => $type,
            'x' => $this->x,
            'y' => $this->y,
            'p' => $path,
            'n' => $name,
        );
        $width = $this->minwidth ? max( $this->minwidth, $width+1 ) : $width+1;
        $height = $this->minheight ? max( $this->minheight, $height+1 ) : $height+1;
        // increase bounds
        $this->width  = max( $this->width, $this->x + $width );
        $this->height = max( $this->height, $this->y + $height );
        // check wrapping
        if( $this->wrapnum ){
            // @todo ..
        }
        // increment to right
        if( $this->horiz ){
            $this->x += $width;
        }
        // else increment down
        else {
            $this->r++;
            $this->x = 0;
            $this->y += $height;
        }
        //
        return $this;
    }
    
    
    
    /**
     * 
     */
    public function set_scale( $scale ){
        if( $scale && '1' !== $scale ){
            $scale = floatval($scale) and
            $this->scale = $scale;
        }
        return $this->scale;
    }

    
    
    /**
     * 
     */
    private function scale( $n ){
        if( $this->scale ){
            $n = (int) ceil( $this->scale * $n );
        }
        return $n;
    }    


    
    /**
     * 
     */
    public function get_css( $prefix = 'sprite' ){
        $w = $this->minwidth  ? $this->scale($this->minwidth)  : 0;
        $h = $this->minheight ? $this->scale($this->minheight) : 0;
        $lines[] = sprintf(
            '.%s { background: url(%1$s.png) no-repeat; display: inline-block; min-width: %upx; min-height: %upx; }', 
            $prefix, $w, $h
        );
        foreach( $this->rows as $row ){
            foreach( $row as $cell ){
                extract( $cell );
                $lines[] = sprintf(
                    '.%s-%s { background-position: -%upx -%upx; }', 
                    $prefix, $n, $this->scale($x), $this->scale($y) 
                );
            }
        }
        return $lines;
    }    
    
    
    
    /**
     * Build final sprite image
     */
    public function compile(){
        $w = $this->width;
        $h = $this->height;
        if( $this->scale ){
            $w = (int) ceil( $this->scale * $w );
            $h = (int) ceil( $this->scale * $h );
        }
        // create transparent canvas to start
        $sprite = imagecreatetruecolor( $w, $h );
        imagesavealpha( $sprite, true );
        imagefill( $sprite, 0, 0, imagecolorallocatealpha( $sprite, 0xFF, 0xFF, 0xFF, 127 ) );
        // superimpose all image files
        foreach( $this->rows as $row ){
            foreach( $row as $cell ){
                extract( $cell, EXTR_PREFIX_ALL, 'src' );
                extract( $cell, EXTR_PREFIX_ALL, 'dst' );
                $gd = imagecreatefromstring( file_get_contents($src_p) );
                if( $this->scale ){
                    $dst_x = $this->scale($dst_x);
                    $dst_y = $this->scale($dst_y);
                    $dst_w = $this->scale($dst_w);
                    $dst_h = $this->scale($dst_h);
                }
                if( ! imagecopyresampled( $sprite, $gd, $dst_x, $dst_y , 0, 0, $dst_w, $dst_h, $src_w, $src_w ) ){
                    throw new Exception('Failed to composite '.$p);
                }
            }
        }
        return $sprite;
    }
    
    
    
}









