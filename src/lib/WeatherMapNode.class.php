<?php
// PHP Weathermap 0.97b
// Copyright Howard Jones, 2005-2012 howie@thingy.com
// http://www.network-weathermap.com/
// Released under the GNU Public License

class WMNodeIcon
{
    protected $type;
    protected $boundingBox;
    protected $node;

    protected $iconscalew;
    protected $iconscaleh;

    protected $iconImageRef;

    public function __construct($node)
    {
        $this->type = "";
        $this->node = $node;
        $this->boundingBox = new WMBoundingBox();
        $this->boundingBox->addWMPoint($node->getPosition());

        $this->iconfile = $node->iconfile;
        $this->iconscalew = $node->iconscalew;
        $this->iconscaleh = $node->iconscaleh;
    }

    public function calculateGeometry()
    {

    }

    public function draw($imageRef, $icon_x1, $icon_y1)
    {
        imagecopy($imageRef, $this->iconImageRef, $icon_x1, $icon_y1, 0, 0, imagesx($this->iconImageRef), imagesy($this->iconImageRef));
        imagedestroy($this->iconImageRef);
    }

    public function getImageRef()
    {
        return $this->iconImageRef;
    }
}

class WMNodeImageIcon extends WMNodeIcon
{
    public function __construct($node)
    {
        parent::__construct();
    }

    public function preRender()
    {
        $realiconfile = $this->map->ProcessString($this->iconfile, $this);

        if (is_readable($realiconfile)) {
            imagealphablending($im, true);

            // draw the supplied icon, instead of the labelled box
            $iconImageRef = imagecreatefromfile($realiconfile);

            if (true === isset($iconFillColour)) {
                $this->colourizeImage($iconImageRef, $iconFillColour);
            }

            if ($iconImageRef) {
                $icon_w = imagesx($iconImageRef);
                $icon_h = imagesy($iconImageRef);

                if (($this->iconscalew * $this->iconscaleh) > 0) {
                    wm_debug("If this is the last thing in your logs, you probably have a buggy GD library. Get > 2.0.33 or use PHP builtin.\n");

                    imagealphablending($iconImageRef, true);

                    wm_debug("SCALING ICON here\n");
                    if ($icon_w > $icon_h) {
                        $scalefactor = $icon_w / $this->iconscalew;
                    } else {
                        $scalefactor = $icon_h / $this->iconscaleh;
                    }
                    $new_width = $icon_w / $scalefactor;
                    $new_height = $icon_h / $scalefactor;
                    $scaled = imagecreatetruecolor($new_width, $new_height);
                    imagealphablending($scaled, false);
                    imagecopyresampled($scaled, $iconImageRef, 0, 0, 0, 0, $new_width, $new_height, $icon_w, $icon_h);
                    imagedestroy($iconImageRef);
                    $iconImageRef = $scaled;

                }
            } else {
                wm_warn("Couldn't open ICON: '" . $realiconfile . "' - is it a PNG, JPEG or GIF? [WMWARN37]\n");
            }
        } else {
            if ($realiconfile != 'none') {
                wm_warn("ICON '" . $realiconfile . "' does not exist, or is not readable. Check path and permissions. [WMARN38]\n");
            }
        }
    }
}

class WMNodeArtificialIcon extends WMNodeIcon
{
//    protected $iconfile;
//    protected $name;

    protected $aiconFillColour;
    protected $aiconInkColour;
    protected $iconFillColour;
    protected $aiconOutlineColour;
    protected $labelFillColour;

    public function __construct($node, $aiconInkColour, $aiconFillColour, $iconFillColour, $aiconOutlineColour, $labelFillColour)
    {
        parent::__construct($node);

        $this->iconfile = $node->iconfile;
        $this->iconscalew = $node->iconscalew;
        $this->iconscaleh = $node->iconscaleh;
        $this->name = $node->name;

        if (! self::isAICONName($this->iconfile)) {
            throw new WMException("AICON with invalid type");
        }

        $this->aiconOutlineColour = $aiconOutlineColour;
        $this->aiconFillColour = $aiconFillColour;
        $this->iconFillColour = $iconFillColour;
        $this->aiconInkColour = $aiconInkColour;
        $this->labelFillColour = $labelFillColour;
    }

    public static function isAICONName($name)
    {
        $artificialIconNames = array('rbox', 'round', 'box', 'inpie', 'outpie', 'gauge', 'nink');

        return in_array($name, $artificialIconNames);
    }

    public function preRender()
    {
        wm_debug("Artificial Icon type " . $this->iconfile . " for $this->name\n");
        // this is an artificial icon - we don't load a file for it

        $this->createEmptyImage();

        $fill = null;
        $ink = null;

//        $aiconFillColour = $this->aiconfillcolour;
//        $aiconInkColour = $this->aiconoutlinecolour;

        // if useiconscale isn't set, then use the static colour defined, or copy the colour from the label
        if ($this->useiconscale == "none") {
            if ($aiconFillColour->isCopy() && !$labelFillColour->isNone()) {
                $fill = $labelFillColour;
            } else {
                if ($aiconFillColour->isRealColour()) {
                    $fill = $aiconFillColour;
                }
            }
        } else {
            // if useiconscale IS defined, use that to figure out the fill colour

            $fill = $iconFillColour;
        }

        // support 'none' and 'copy' for AICON outlines too
        if (!$this->aiconoutlinecolour->isNone() && $this->aiconoutlinecolor->isCopy()) {
            $ink = $labelFillColour;
        } else {
            if ($aiconOutlineColour->isRealColour()) {
                $ink = $aiconInkColour;
            }
        }

        wm_debug("AICON colours are $ink and $fill\n");

        if ($this->iconfile == 'box') {
            $this->drawAIconBox($this->iconImageRef, $fill, $ink);
        }

        if ($this->iconfile == 'rbox') {
            $this->drawAIconRoundedBox($this->iconImageRef, $fill, $ink);
        }

        if ($this->iconfile == 'round') {
            $this->drawAIconRound($this->iconImageRef, $fill, $ink);
        }

        if ($this->iconfile == 'nink') {
            $this->drawAIconNINK($this->iconImageRef, $ink);
        }

        // XXX - needs proper colours
        if ($this->iconfile == 'inpie' || $this->iconfile == 'outpie') {
            $this->drawAIconPie($this->iconImageRef, $fill, $ink);
        }

        if ($this->iconfile == 'gauge') {
            wm_warn('gauge AICON not implemented yet [WMWARN99]');
        }
    }

    /**
     * @param $iconImageRef
     * @param $ink
     */
    protected function drawAIconNINK($iconImageRef, $ink)
    {
        $radiusX = $this->iconscalew / 2 - 1;
        $radiusY = $this->iconscaleh / 2 - 1;
        $size = $this->iconscalew;
        $quarter = $size / 4;

        $colour1 = $this->node->colours[OUT]->gdallocate($iconImageRef);
        $colour2 = $this->node->colours[IN]->gdallocate($iconImageRef);

        imagefilledarc($iconImageRef, $radiusX - 1, $radiusY, $size, $size, 270, 90, $colour1, IMG_ARC_PIE);
        imagefilledarc($iconImageRef, $radiusX + 1, $radiusY, $size, $size, 90, 270, $colour2, IMG_ARC_PIE);

        imagefilledarc($iconImageRef, $radiusX - 1, $radiusY + $quarter, $quarter * 2, $quarter * 2, 0, 360, $colour1, IMG_ARC_PIE);
        imagefilledarc($iconImageRef, $radiusX + 1, $radiusY - $quarter, $quarter * 2, $quarter * 2, 0, 360, $colour2, IMG_ARC_PIE);

        if ($ink !== null && !$ink->isNone()) {
            // XXX - need a font definition from somewhere for NINK text
            $font = 1;
            $inkGD = $ink->gdallocate($iconImageRef);

            $directions = array(
                array("in", -1),
                array("out", +1)
            );

            foreach ($directions as $direction) {
                $name = $direction[0];
                $label = $this->owner->ProcessString("{node:this:bandwidth_$name:%.1k}", $this);

                list($twid, $thgt) = $this->owner->myimagestringsize($font, $label);
                $this->owner->myimagestring($iconImageRef, $font, $radiusX - $twid / 2, $radiusY + $direction[1] * $quarter + ($thgt / 2), $label, $inkGD);
            }

            imageellipse($iconImageRef, $radiusX, $radiusY, $radiusX * 2, $radiusY * 2, $inkGD);
        }
    }

    /**
     * @param $fill
     * @param $iconImageRef
     * @param $ink
     */
    protected function drawAIconRound($iconImageRef, $fill, $ink)
    {
        $radiusX = $this->iconscalew / 2 - 1;
        $radiusY = $this->iconscaleh / 2 - 1;

        if ($fill !== null && !$fill->isNone()) {
            imagefilledellipse($iconImageRef, $radiusX, $radiusY, $radiusX * 2, $radiusY * 2, $fill->gdAllocate($iconImageRef));
        }

        if ($ink !== null && !$ink->isNone()) {
            imageellipse($iconImageRef, $radiusX, $radiusY, $radiusX * 2, $radiusY * 2, $ink->gdallocate($iconImageRef));
        }
    }

    /**
     * @param $iconImageRef
     * @param $fill
     * @param $ink
     */
    protected function drawAIconRoundedBox($iconImageRef, $fill, $ink)
    {
        if ($fill !== null && !$fill->isNone()) {
            imagefilledroundedrectangle($iconImageRef, 0, 0, $this->iconscalew - 1, $this->iconscaleh - 1, 4, $fill->gdAllocate($iconImageRef));
        }

        if ($ink !== null && !$ink->isNone()) {
            imageroundedrectangle($iconImageRef, 0, 0, $this->iconscalew - 1, $this->iconscaleh - 1, 4, $ink->gdallocate($iconImageRef));
        }
    }

    /**
     * @param $iconImageRef
     * @param $fill
     * @param $ink
     */
    protected function drawAIconBox($iconImageRef, $fill, $ink)
    {
        if ($fill !== null && !$fill->isNone()) {
            imagefilledrectangle($iconImageRef, 0, 0, $this->iconscalew - 1, $this->iconscaleh - 1, $fill->gdAllocate($iconImageRef));
        }

        if ($ink !== null && !$ink->isNone()) {
            imagerectangle($iconImageRef, 0, 0, $this->iconscalew - 1, $this->iconscaleh - 1, $ink->gdallocate($iconImageRef));
        }
    }

    /**
     * @param $fill
     * @param $iconImageRef
     * @param $ink
     */
    protected function drawAIconPie($iconImageRef, $fill, $ink)
    {
        if ($this->iconfile == 'inpie') {
            $segment_angle = (($this->percentUsages[IN]) / 100) * 360;
        }
        if ($this->iconfile == 'outpie') {
            $segment_angle = (($this->percentUsages[OUT]) / 100) * 360;
        }

        $radiusX = $this->iconscalew / 2 - 1;
        $radiusY = $this->iconscaleh / 2 - 1;

        if ($fill !== null && !$fill->isNone()) {
            imagefilledellipse($iconImageRef, $radiusX, $radiusY, $radiusX * 2, $radiusY * 2, $fill->gdAllocate($iconImageRef));
        }

        if ($ink !== null && !$ink->isNone()) {
            imagefilledarc($iconImageRef, $radiusX, $radiusY, $radiusX * 2, $radiusY * 2, 0, $segment_angle, $ink->gdallocate($iconImageRef), IMG_ARC_PIE);
        }

        if ($fill !== null && !$fill->isNone()) {
            imageellipse($iconImageRef, $radiusX, $radiusY, $radiusX * 2, $radiusY * 2, $fill->gdAllocate($iconImageRef));
        }
    }

    protected function createEmptyImage()
    {
        $this->iconImageRef = imagecreatetruecolor($this->iconscalew, $this->iconscaleh);
        imageSaveAlpha($this->iconImageRef, true);

        $nothing = imagecolorallocatealpha($this->iconImageRef, 128, 0, 0, 127);
        imagefill($this->iconImageRef, 0, 0, $nothing);
    }
}

class WMNodeLabel
{
    private $boundingBox;
    private $node;
    private $map;

    private $textPosition;
    private $labelPosition;
    private $labelAngle;
    private $labelString;
    private $labelFont;
    private $labelRectangle;

    private $x;
    private $y;
    private $name;

    private $labelFillColour;
    private $labelOutlineColour;
    private $labelShadowColour;
    private $labelTextColour;

    public function __construct($node)
    {
        $this->node = $node;
        $this->map = $node->owner;

        $position = $node->getPosition();

        $this->boundingBox = new WMBoundingBox();
        $this->boundingBox->addWMPoint($position);
        $this->textPosition = new WMPoint(0, 0);
        $this->labelPosition = new WMPoint(0, 0);
        $this->labelAngle = $node->labelangle;

        $this->x = $position->x;
        $this->y = $position->y;
        $this->name = $node->name;
    }

    public function calculateGeometry($labelString, $labelFont)
    {
        $this->labelFont = $labelFont;
        $this->labelString = $labelString;

        $padding = 4.0;
        $padFactor = 1.0;

        list($stringWidth, $stringHeight) = $this->map->myimagestringsize($labelFont, $labelString);

        if ($this->labelAngle == 90 || $this->labelAngle == 270) {
            $boxWidth = ($stringHeight * $padFactor) + $padding;
            $boxHeight = ($stringWidth * $padFactor) + $padding;
        } else {
            $boxWidth = ($stringWidth * $padFactor) + $padding;
            $boxHeight = ($stringHeight * $padFactor) + $padding;
        }

        $halfWidth = $boxWidth / 2;
        $halfHeight = $boxHeight / 2;

        $this->labelRectangle = new WMRectangle($this->x - $halfWidth, $this->y - $halfHeight, $this->x + $halfWidth, $this->y + $halfHeight);

//        $label_x1 = $this->x - $halfWidth;
//        $label_y1 = $this->y - $halfHeight;
//
//        $label_x2 = $this->x + $halfWidth;
//        $label_y2 = $this->y + $halfHeight;

        $this->boundingBox->addPoint($this->x - $halfWidth, $this->y - $halfHeight);
        $this->boundingBox->addPoint($this->x + $halfWidth, $this->y + $halfHeight);

        wm_debug("Node->pre_render: " . $this->name . " Label Metrics are: $stringWidth x $stringHeight -> $boxWidth x $boxHeight\n");

        $textPosition = $this->node->getPosition();

        $stringHalfHeight = $stringHeight / 2;
        $stringHalfWidth = $stringWidth / 2;

        if ($this->labelAngle == 90) {
            $textPosition->translate($stringHalfHeight, $stringHalfWidth);
//            $textX = $this->x + $stringHalfHeight;
//            $textY = $this->y + $stringHalfWidth;
        }

        if ($this->labelAngle == 270) {
            $textPosition->translate(-$stringHalfHeight, - $stringHalfWidth);
//            $textX = $this->x - ($stringHalfHeight);
//            $textY = $this->y - ($stringHalfWidth);
        }

        if ($this->labelAngle == 0) {
            $textPosition->translate(-$stringHalfWidth, $stringHalfHeight);
//            $textX = $this->x - ($stringHalfWidth);
//            $textY = $this->y + ($stringHalfHeight);
        }

        if ($this->labelAngle == 180) {
            $textPosition->translate($stringHalfWidth, -$stringHalfHeight);
//            $textX = $this->x + ($stringHalfWidth);
//            $textY = $this->y - ($stringHalfHeight);
        }
//        $this->textPosition = new WMPoint($textX, $textY);
        $this->textPosition = $textPosition;

        $this->width = $boxWidth;
        $this->height = $boxHeight;
    }

    public function translate($deltaX, $deltaY)
    {
        $this->textPosition->translate($deltaX, $deltaY);
    }


    public function preRender($labelFillColour, $labelOutlineColour, $labelShadowColour, $labelTextColour)
    {
        $this->labelFillColour = $labelFillColour;
        $this->labelOutlineColour = $labelOutlineColour;
        $this->labelShadowColour = $labelShadowColour;
        $this->labelTextColour = $labelTextColour;
    }

    public function draw($imageRef)
    {
        $txt_x = $this->textPosition->x;
        $txt_y = $this->textPosition->y;

        // XXX - this had better be temporary!
        $label_x1 = $this->labelRectangle->topLeft->x;
        $label_y1 = $this->labelRectangle->topLeft->y;
        $label_x2 = $this->labelRectangle->bottomRight->x;
        $label_y2 = $this->labelRectangle->bottomRight->y;

        // if there's an icon, then you can choose to have no background

        if (!$this->labelFillColour->isNone()) {
            imagefilledrectangle($imageRef, $label_x1, $label_y1, $label_x2, $label_y2, $this->labelFillColour->gdAllocate($imageRef));
        }

        if ($this->map->selected) {
            imagerectangle($imageRef, $label_x1, $label_y1, $label_x2, $label_y2, $this->map->selected);
            // would be nice if it was thicker, too...
            imagerectangle($imageRef, $label_x1 + 1, $label_y1 + 1, $label_x2 - 1, $label_y2 - 1, $this->map->selected);
        } else {
            // $label_outline_colour = $this->labeloutlinecolour;
            if ($this->labelOutlineColour->isRealColour()) {
                imagerectangle($imageRef, $label_x1, $label_y1, $label_x2, $label_y2, $this->labelOutlineColour->gdallocate($imageRef));
            }
        }

        // $shcol = $this->labelfontshadowcolour;
        if ($this->labelShadowColour->isRealColour()) {
            $this->map->myimagestring(
                $imageRef,
                $this->labelfont,
                $txt_x + 1,
                $txt_y + 1,
                $this->processedLabel,
                $this->labelShadowColour->gdallocate($imageRef),
                $this->labelangle
            );
        }

        $txcol = $this->labelTextColour;

        if ($txcol->isContrast()) {
            if ($this->labelFillColour->isRealColour()) {
                $txcol = $this->labelFillColour->getContrastingColour();
            } else {
                wm_warn("You can't make a contrast with 'none'. [WMWARN43]\n");
                $txcol = new WMColour(0, 0, 0);
            }
        }

        $this->map->myimagestring(
            $imageRef,
            $this->node->labelfont,
            $txt_x,
            $txt_y,
            $this->node->processedLabel,
            $txcol->gdAllocate($imageRef),
            $this->labelAngle
        );
    }
}

class WeatherMapNode extends WeatherMapDataItem
{
    public $x;
    public $y;
    public $position; // to replace the above eventually
    public $original_x;
    public $original_y;
    public $relative_resolved;
    public $width;
    public $height;
    public $label; // the configured label text
    public $processedLabel; // the label after processing (what is actually drawn)
    public $labelfont;
    public $labelangle;

    // SCALE colours

    public $notestext = array();

    public $selected = 0;
    public $iconfile;
    public $iconscalew;
    public $iconscaleh;
    public $labeloffset;
    public $labeloffsetx;
    public $labeloffsety;

    public $labelbgcolour;
    public $labeloutlinecolour;
    public $labelfontcolour;
    public $labelfontshadowcolour;
    public $cachefile;
    public $useiconscale;
    public $iconscaletype;
    public $iconscalevar;

    public $image;
    public $centre_x;
    public $centre_y;
    public $relative_to;
    public $polar;
    public $boundingboxes = array();
    public $named_offsets = array();

    public $runtime = array();

    function __construct($name, $template, $owner)
    {
        parent::__construct();

        $this->name = $name;
        $this->owner = $owner;
        $this->template = $template;

        $this->inherit_fieldlist = array
        (
            'boundingboxes' => array(),
            'named_offsets' => array(),
            'my_default' => null,
            'label' => '',
            'proclabel' => '',
            'usescale' => 'DEFAULT',
            'scaletype' => 'percent',
            'iconscaletype' => 'percent',
            'useiconscale' => 'none',
            'scalevar' => 'in',
            'template' => ':: DEFAULT ::',
            'iconscalevar' => 'in',
            'labelfont' => 3,
            'relative_to' => '',
            'relative_name' => '',
            'relative_resolved' => false,
            'x' => null,
            'y' => null,
            'inscalekey' => '', 'outscalekey' => '',
            'original_x' => 0,
            'original_y' => 0,
            'inpercent' => 0,
            'outpercent' => 0,
            'labelangle' => 0,
            'iconfile' => '',
            'iconscalew' => 0,
            'iconscaleh' => 0,
            'targets' => array(),
            'infourl' => array(IN => '', OUT => ''),
            'notestext' => array(IN => '', OUT => ''),
            'notes' => array(),
            'hints' => array(),
            'overliburl' => array(IN => array(), OUT => array()),
            'overlibwidth' => 0,
            'overlibheight' => 0,
            'overlibcaption' => array(IN => '', OUT => ''),
            'labeloutlinecolour' => new WMColour(0, 0, 0),
            'labelbgcolour' => new WMColour(255, 255, 255),
            'labelfontcolour' => new WMColour(0, 0, 0),
            'labelfontshadowcolour' => new WMColour('none'),
            'aiconoutlinecolour' => new WMColour(0, 0, 0),
            'aiconfillcolour' => new WMColour('copy'), // copy from the node label
            'labeloffset' => '',
            'labeloffsetx' => 0,
            'labeloffsety' => 0,
            'zorder' => 600,
            'max_bandwidth_in' => 100,
            'max_bandwidth_out' => 100,
            'max_bandwidth_in_cfg' => '100',
            'max_bandwidth_out_cfg' => '100'
        );

        $this->width = 0;
        $this->height = 0;
        $this->centre_x = 0;
        $this->centre_y = 0;
        $this->polar = false;
        $this->pos_named = false;
        $this->image = null;

        $this->reset($owner);
    }

    function isTemplate()
    {
        return is_null($this->x);
    }

    function my_type()
    {
        return "NODE";
    }

    public function getPosition()
    {
        return new WMPoint($this->x, $this->y);
    }

    public function setPosition($point)
    {
        $this->x = $point->x;
        $this->y = $point->y;
        $this->position = $point;
    }

    public function cleanUp()
    {
        parent::cleanUp();

        if (isset($this->image)) {
            imagedestroy($this->image);
        }
        $this->owner = null;
        $this->parent = null;
        $this->descendents = null;
    }



    /**
     * @return WMColour
     */
    protected function calculateFillColour()
    {
        $labelFillColour = new WMColour('none');

        // if a target is specified, and you haven't forced no background, then the background will
        // come from the SCALE in USESCALE
        if (!empty($this->targets) && $this->usescale != 'none') {
            if ($this->scalevar == 'in') {
                $labelFillColour = $this->colours[IN];

            }

            if ($this->scalevar == 'out') {
                $labelFillColour = $this->colours[OUT];
                return $labelFillColour;
            }
            return $labelFillColour;
        } else {
            $labelFillColour = $this->labelbgcolour;
            return $labelFillColour;
        }
    }

    /**
     * @return null
     */
    protected function calculateIconFillColour()
    {
        $iconFillColour = null;
        if (!empty($this->targets) && $this->useiconscale != 'none') {
            wm_debug("Colorising the icon\n");
            $percent = 0;
            $val = 0;

            if ($this->iconscalevar == 'in') {
                $percent = $this->percentUsages[IN];
                $val = $this->absoluteUsages[IN];
            }
            if ($this->iconscalevar == 'out') {
                $percent = $this->percentUsages[OUT];
                $val = $this->absoluteUsages[OUT];
            }

            if ($this->iconscaletype == 'percent') {
                list($iconFillColour, $node_iconscalekey, $icontag) =
                    $this->owner->scales[$this->useiconscale]->ColourFromValue($percent, $this->name);
                return $iconFillColour;
            } else {
                // use the absolute value if we aren't doing percentage scales.
                list($iconFillColour, $node_iconscalekey, $icontag) =
                    $this->owner->scales[$this->useiconscale]->ColourFromValue($val, $this->name, false);
                return $iconFillColour;
            }
        }
        return $iconFillColour;
    }

    private function getDirectionList()
    {
        if ($this->scalevar == 'in') {
            return array(IN);
        }
        return array(OUT);
    }

    /***
     * precalculate the colours to be used, and the bounding boxes for labels and icons (if they exist)
     *
     * This is the only stuff that needs to be done if we're doing an editing pass. No actual drawing is necessary.
     *
     * TODO: write this.
     */
    function preCalculate(&$owner)
    {
        // don't bother doing anything if it's a template
        if ($this->isTemplate()) {
            return;
        }

        // apparently, some versions of the gd extension will crash
        // if we continue...
        if ($this->label == '' && $this->iconfile == '') {
            return;
        }

        // First, figure out the icon

        // Next, figure out the label

        // Finally, the colours
    }

    function colourizeImage($imageRef, $tintColour)
    {

        list ($red, $green, $blue) = $tintColour->getComponents();

        // TODO - bug? Shouldn't this be per-map and not per-node?
        // Also, if this was a parameter to this function, it wouldn't need to be a method at all
        if (function_exists("imagefilter") && $this->get_hint("use_imagefilter") == 1) {
            imagefilter($imageRef, IMG_FILTER_COLORIZE, $red, $green, $blue);
        } else {
            imagecolorize($imageRef, $red, $green, $blue);
        }
    }

    // make a mini-image, containing this node and nothing else
    // figure out where the real NODE centre is, relative to the top-left corner.
    function preRender($im, &$map)
    {
        // don't bother drawing if it's a template
        if ($this->isTemplate()) {
            return;
        }

        // apparently, some versions of the gd extension will crash if we continue...
        if ($this->label == '' && $this->iconfile == '') {
            return;
        }

        $iconObj = null;
        $labelObj = null;

        $labelFillColour = $this->calculateFillColour();
        $iconFillColour = $this->calculateIconFillColour();

        // start these off with sensible values, so that bbox
        // calculations are easier.

        $icon_x1 = $this->x;
        $icon_x2 = $this->x;
        $icon_y1 = $this->y;
        $icon_y2 = $this->y;

        $label_x1 = $this->x;
        $label_x2 = $this->x;
        $label_y1 = $this->y;
        $label_y2 = $this->y;

        $boxWidth = 0;
        $boxHeight = 0;

        $icon_w = 0;
        $icon_h = 0;

        // figure out a bounding rectangle for the label
        if ($this->label != '') {
            $labelObj = new WMNodeLabel($this);

            $this->processedLabel = $map->ProcessString($this->label, $this, true, true);

            // if screenshot_mode is enabled, wipe any letters to X and wipe any IP address to 127.0.0.1
            // hopefully that will preserve enough information to show cool stuff without leaking info
            if ($map->get_hint('screenshot_mode') == 1) {
                $this->processedLabel = WMUtility::stringAnonymise($this->processedLabel);
            }

            $labelObj->calculateGeometry($this->processedLabel, $this->labelfont);

            $labelObj->preRender($labelFillColour, $this->labeloutlinecolour, $this->labelfontshadowcolour, $this->labelfontcolour);

//            $padding = 4.0;
//            $padFactor = 1.0;
//
//
//            list($stringWidth, $stringHeight) = $map->myimagestringsize($this->labelfont, $this->processedLabel);
//
//            if ($this->labelangle == 90 || $this->labelangle == 270) {
//                $boxWidth = ($stringHeight * $padFactor) + $padding;
//                $boxHeight = ($stringWidth * $padFactor) + $padding;
//            } else {
//                $boxWidth = ($stringWidth * $padFactor) + $padding;
//                $boxHeight = ($stringHeight * $padFactor) + $padding;
//            }
//
//            $halfWidth = $boxWidth / 2;
//            $halfHeight = $boxHeight / 2;
//
//            $label_x1 = $this->x - $halfWidth;
//            $label_y1 = $this->y - $halfHeight;
//
//            $label_x2 = $this->x + $halfWidth;
//            $label_y2 = $this->y + $halfHeight;
//
//            wm_debug("Node->pre_render: " . $this->name . " Label Metrics are: $stringWidth x $stringHeight -> $boxWidth x $boxHeight\n");
//
//            if ($this->labelangle == 90) {
//                $txt_x = $this->x + ($stringHeight / 2);
//                $txt_y = $this->y + ($stringWidth / 2);
//            }
//
//            if ($this->labelangle == 270) {
//                $txt_x = $this->x - ($stringHeight / 2);
//                $txt_y = $this->y - ($stringWidth / 2);
//            }
//
//            if ($this->labelangle == 0) {
//                $txt_x = $this->x - ($stringWidth / 2);
//                $txt_y = $this->y + ($stringHeight / 2);
//            }
//
//            if ($this->labelangle == 180) {
//                $txt_x = $this->x + ($stringWidth / 2);
//                $txt_y = $this->y - ($stringHeight / 2);
//            }
//
//            $this->width = $boxWidth;
//            $this->height = $boxHeight;
        }

        // figure out a bounding rectangle for the icon
        if ($this->iconfile != '') {
            $iconImageRef = null;
            $icon_w = 0;
            $icon_h = 0;

//            $artificialIconNames = array('rbox', 'round', 'box', 'inpie', 'outpie', 'gauge', 'nink');

            //if (in_array($this->iconfile, $artificialIconNames)) {
            if (WMNodeArtificialIcon::isAICONName($this->iconfile)) {
                $iconObj = new WMNodeArtificialIcon($this, $aiconInkColour, $aiconFillColour, $iconFillColour, $aiconOutlineColour, $labelFillColour);
            } else {
                $iconObj = new WMNodeImageIcon($this);
            }

            $iconObj->calculateGeometry();
            $iconObj->preRender();

            $iconImageRef = $iconObj->getImageRef();

            if ($iconImageRef) {
                $icon_w2 = imagesx($iconImageRef) / 2;
                $icon_h2 = imagesy($iconImageRef) / 2;
                $iconRect = new WMRectangle(-$icon_w2, -$icon_h2, $icon_w2, $icon_h2);
                $iconRect->translate($this->x, $this->y);

                $icon_w = imagesx($iconImageRef);
                $icon_h = imagesy($iconImageRef);

                $icon_x1 = $this->x - $icon_w / 2;
                $icon_y1 = $this->y - $icon_h / 2;
                $icon_x2 = $this->x + $icon_w / 2;
                $icon_y2 = $this->y + $icon_h / 2;

                $this->width = imagesx($iconImageRef);
                $this->height = imagesy($iconImageRef);

                $this->boundingboxes[] = array($icon_x1, $icon_y1, $icon_x2, $icon_y2);
            }
        }



        // do any offset calculations
        $deltaX = 0;
        $deltaY = 0;
        if (($this->labeloffset != '') && (($this->iconfile != ''))) {
            $this->labeloffsetx = 0;
            $this->labeloffsety = 0;

            list($deltaX, $deltaY) = WMUtility::calculateOffset(
                $this->labeloffset,
                ($icon_w + $boxWidth - 1),
                ($icon_h + $boxHeight)
            );
        }


        $label_x1 += ($this->labeloffsetx + $deltaX);
        $label_x2 += ($this->labeloffsetx + $deltaX);
        $label_y1 += ($this->labeloffsety + $deltaY);
        $label_y2 += ($this->labeloffsety + $deltaY);

        if ($this->label != '') {
            $this->boundingboxes[] = array($label_x1, $label_y1, $label_x2, $label_y2);
        }

        // work out the bounding box of the whole thing

        $totalBoundingBox = new WMBoundingBox();
//        $totalBoundingBox->addRectangle();
//        $totalBoundingBox->addRectangle();

        $bbox_x1 = min($label_x1, $icon_x1);
        $bbox_x2 = max($label_x2, $icon_x2) + 1;
        $bbox_y1 = min($label_y1, $icon_y1);
        $bbox_y2 = max($label_y2, $icon_y2) + 1;

        // create TWO imagemap entries - one for the label and one for the icon
        // (so we can have close-spaced icons better)

        $temp_width = $bbox_x2 - $bbox_x1;
        $temp_height = $bbox_y2 - $bbox_y1;
        // create an image of that size and draw into it
        $node_im = imagecreatetruecolor($temp_width, $temp_height);
        // ImageAlphaBlending($node_im, false);
        imageSaveAlpha($node_im, true);

        $nothing = imagecolorallocatealpha($node_im, 128, 0, 0, 127);
        imagefill($node_im, 0, 0, $nothing);

        $label_x1 -= $bbox_x1;
        $label_x2 -= $bbox_x1;
        $label_y1 -= $bbox_y1;
        $label_y2 -= $bbox_y1;

        $icon_x1 -= $bbox_x1;
        $icon_y1 -= $bbox_y1;


        $labelObj->translate( -$bbox_x1 + $deltaX + $this->labeloffsetx, -$bbox_y1 + $deltaY + $this->labeloffsety);

        // Draw the icon, if any
        if (isset($iconImageRef)) {
            $iconObj->draw($node_im);
//            imagecopy($node_im, $iconImageRef, $icon_x1, $icon_y1, 0, 0, imagesx($iconImageRef), imagesy($iconImageRef));
//            imagedestroy($iconImageRef);
        }

        // Draw the label, if any
        if ($this->label != '') {
          $labelObj->draw($node_im);
        }

        $this->centre_x = $this->x - $bbox_x1;
        $this->centre_y = $this->y - $bbox_y1;

        $this->image = $node_im;
    }

    function isRelativePositionResolved()
    {
        return $this->relative_resolved;
    }

    function isRelativePositioned()
    {
        if ($this->relative_to != "") {
            return true;
        }
        return false;
    }

    function getRelativeAnchor()
    {
        return $this->relative_to;
    }

    function resolveRelativePosition($anchorNode)
    {
        $anchorPosition = $anchorNode->getPosition();

        if ($this->polar) {
            // treat this one as a POLAR relative coordinate.
            // - draw rings around a node!
            $angle = $this->x;
            $distance = $this->y;

            $now = $anchorPosition->copy();
            $now->translatePolar($angle, $distance);
            wm_debug("POLAR $this -> $now\n");
            $this->setPosition($now);
            $this->relative_resolved = true;
            return true;
        }

        if ($this->pos_named) {
            $off_name = $this->relative_name;
            if (isset($anchorNode->named_offsets[$off_name])) {
                $now = $anchorPosition->copy();
                $now->translate(
                    $anchorNode->named_offsets[$off_name][0],
                    $anchorNode->named_offsets[$off_name][1]
                );
                wm_debug("NAMED OFFSET $this -> $now\n");
                $this->setPosition($now);
                $this->relative_resolved = true;
                return true;
            }
            wm_debug("Fell through named offset.\n");
            return false;
        }

        // resolve the relative stuff
        $now = $this->getPosition();
        $now->translate($anchorPosition->x, $anchorPosition->y);

        wm_debug("OFFSET $this -> $now\n");
        $this->setPosition($now);
        $this->relative_resolved = true;

        return true;
    }

    // draw the node, using the pre_render() output
    function draw($im, &$map)
    {
        // take the offset we figured out earlier, and just blit the image on. Who says "blit" anymore?

        // it's possible that there is no image, so better check.
        if (isset($this->image)) {
            imagealphablending($im, true);
            imagecopy($im, $this->image, $this->x - $this->centre_x, $this->y - $this->centre_y, 0, 0, imagesx($this->image), imagesy($this->image));
        }

        // XXX - Hiding this here so Weathermap::drawMapImage doesn't need to know about it
        $this->makeImageMapAreas();
    }

    private function makeImageMapAreas()
    {
        $index = 0;
        foreach ($this->boundingboxes as $bbox) {
            $areaName = "NODE:N" . $this->id . ":" . $index;
            $newArea = new HTML_ImageMap_Area_Rectangle($areaName, "", array($bbox));
            wm_debug("Adding imagemap area");
            $this->imageMapAreas[] = $newArea;
            $this->imap_areas[] = $areaName; // XXX - what is this used for?
            $index++;
        }
    }

    function getTemplateObject()
    {
        return $this->owner->getNode($this->template);
    }

    function copyFrom(&$source)
    {
        wm_debug("Initialising NODE $this->name from $source->name\n");
        assert('is_object($source)');

        foreach (array_keys($this->inherit_fieldlist) as $fld) {
            if ($fld != 'template') {
                $this->$fld = $source->$fld;
            }
        }
    }

    public function getConfig()
    {
        $output = '';

        wm_debug("Writing config for node $this->name\n");
        # $output .= "# ID ".$this->id." - first seen in ".$this->defined_in."\n";

        // This allows the editor to wholesale-replace a single node's configuration
        // at write-time - it should include the leading NODE xyz line (to allow for renaming)
        if ($this->config_override != '') {
            $output = $this->config_override . "\n";
            return ($output);
        }

        // this is our template. Anything we do different should be written
        $default_default = $this->getTemplateObject();

        wm_debug("Writing config for NODE $this->name against $this->template\n");

        $basic_params = array(
            array("fieldName"=>'label', "configKeyword"=>'LABEL', "type"=>CONFIG_TYPE_LITERAL),
            array("fieldName"=>'zorder', "configKeyword"=>'ZORDER', "type"=>CONFIG_TYPE_LITERAL),
            array("fieldName"=>'labeloffset', "configKeyword"=>'LABELOFFSET', "type"=>CONFIG_TYPE_LITERAL),
            array("fieldName"=>'labelfont', "configKeyword"=>'LABELFONT', "type"=>CONFIG_TYPE_LITERAL),
            array("fieldName"=>'labelangle', "configKeyword"=>'LABELANGLE', "type"=>CONFIG_TYPE_LITERAL),

            array("fieldName"=>'aiconoutlinecolour', "configKeyword"=>'AICONOUTLINECOLOR', "type"=>CONFIG_TYPE_COLOR),
            array("fieldName"=>'aiconfillcolour', "configKeyword"=>'AICONFILLCOLOR', "type"=>CONFIG_TYPE_COLOR),
            array("fieldName"=>'labeloutlinecolour', "configKeyword"=>'LABELOUTLINECOLOR', "type"=>CONFIG_TYPE_COLOR),
            array("fieldName"=>'labelfontshadowcolour', "configKeyword"=>'LABELFONTSHADOWCOLOR', "type"=>CONFIG_TYPE_COLOR),
            array("fieldName"=>'labelbgcolour', "configKeyword"=>'LABELBGCOLOR', "type"=>CONFIG_TYPE_COLOR),
            array("fieldName"=>'labelfontcolour', "configKeyword"=>'LABELFONTCOLOR', "type"=>CONFIG_TYPE_COLOR)
        );

        $output .= $this->getConfigSimple($basic_params, $default_default);

        // IN/OUT are the same, so we can use the simpler form here
        if ($this->infourl[IN] != $default_default->infourl[IN]) {
            $output .= "\tINFOURL " . $this->infourl[IN] . "\n";
        }

        if ($this->overlibcaption[IN] != $default_default->overlibcaption[IN]) {
            $output .= "\tOVERLIBCAPTION " . $this->overlibcaption[IN] . "\n";
        }

        // IN/OUT are the same, so we can use the simpler form here
        if ($this->notestext[IN] != $default_default->notestext[IN]) {
            $output .= "\tNOTES " . $this->notestext[IN] . "\n";
        }

        if ($this->overliburl[IN] != $default_default->overliburl[IN]) {
            $output .= "\tOVERLIBGRAPH " . join(" ", $this->overliburl[IN]) . "\n";
        }

        $val = $this->iconscalew . " " . $this->iconscaleh . " " . $this->iconfile;

        $comparison = $default_default->iconscalew . " " . $default_default->iconscaleh . " " . $default_default->iconfile;

        if ($val != $comparison) {
            $output .= "\tICON ";
            if ($this->iconscalew > 0) {
                $output .= $this->iconscalew . " " . $this->iconscaleh . " ";
            }
            $output .= ($this->iconfile == '' ? 'none' : $this->iconfile) . "\n";
        }

        if ($this->targets != $default_default->targets) {
            $output .= "\tTARGET";

            foreach ($this->targets as $target) {
                $output .= " " . $target->asConfig();
            }
            $output .= "\n";
        }

        $val = $this->usescale . " " . $this->scalevar . " " . $this->scaletype;
        $comparison = $default_default->usescale . " " . $default_default->scalevar . " " . $default_default->scaletype;

        if (($val != $comparison)) {
            $output .= "\tUSESCALE " . $val . "\n";
        }

        $val = $this->useiconscale . " " . $this->iconscalevar;
        $comparison = $default_default->useiconscale . " " . $default_default->iconscalevar;

        if ($val != $comparison) {
            $output .= "\tUSEICONSCALE " . $val . "\n";
        }

        $val = $this->labeloffsetx . " " . $this->labeloffsety;
        $comparison = $default_default->labeloffsetx . " " . $default_default->labeloffsety;

        if ($comparison != $val) {
            $output .= "\tLABELOFFSET " . $val . "\n";
        }

        $val = $this->x . " " . $this->y;
        $comparison = $default_default->x . " " . $default_default->y;

        if ($val != $comparison) {
            if ($this->relative_to == '') {
                $output .= "\tPOSITION " . $val . "\n";
            } else {
                if ($this->polar) {
                    $output .= "\tPOSITION " . $this->relative_to . " " . $this->original_x . "r" . $this->original_y . "\n";
                } elseif ($this->pos_named) {
                    $output .= "\tPOSITION " . $this->relative_to . ":" . $this->relative_name . "\n";
                } else {
                    $output .= "\tPOSITION " . $this->relative_to . " " . $this->original_x . " " . $this->original_y . "\n";
                }
            }
        }

        $output .= $this->getMaxValueConfig($default_default);

        $output .= $this->getConfigHints($default_default);

        foreach ($this->named_offsets as $off_name => $off_pos) {
            // if the offset exists with different values, or
            // doesn't exist at all in the template, we need to write
            // some config for it
            if ((array_key_exists($off_name, $default_default->named_offsets))) {
                $offsetX = $default_default->named_offsets[$off_name][0];
                $offsetY = $default_default->named_offsets[$off_name][1];

                if ($offsetX != $off_pos[0] || $offsetY != $off_pos[1]) {
                    $output .= sprintf("\tDEFINEOFFSET %s %d %d\n", $off_name, $off_pos[0], $off_pos[1]);
                }
            } else {
                $output .= sprintf("\tDEFINEOFFSET %s %d %d\n", $off_name, $off_pos[0], $off_pos[1]);
            }
        }

        if ($output != '') {
            $output = "NODE " . $this->name . "\n$output\n";
        }

        return ($output);
    }

    function asJSCore()
    {
        $output = "";

        $output .= "x:" . (is_null($this->x) ? "'null'" : $this->x) . ", ";
        $output .= "y:" . (is_null($this->y) ? "'null'" : $this->y) . ", ";
        $output .= "\"id\":" . $this->id . ", ";
        $output .= "ox:" . $this->original_x . ", ";
        $output .= "oy:" . $this->original_y . ", ";
        $output .= "relative_to:" . WMUtility::jsEscape($this->relative_to) . ", ";
        $output .= "label:" . WMUtility::jsEscape($this->label) . ", ";
        $output .= "name:" . WMUtility::jsEscape($this->name) . ", ";
        $output .= "infourl:" . WMUtility::jsEscape($this->infourl[IN]) . ", ";
        $output .= "overlibcaption:" . WMUtility::jsEscape($this->overlibcaption[IN]) . ", ";
        $output .= "overliburl:" . WMUtility::jsEscape(join(" ", $this->overliburl[IN])) . ", ";
        $output .= "overlibwidth:" . $this->overlibheight . ", ";
        $output .= "overlibheight:" . $this->overlibwidth . ", ";
        if (sizeof($this->boundingboxes) > 0) {
            $output .= sprintf("bbox:[%d,%d, %d,%d], ", $this->boundingboxes[0][0], $this->boundingboxes[0][1], $this->boundingboxes[0][2], $this->boundingboxes[0][3]);
        } else {
            $output .= "bbox: [], ";
        }

        if (preg_match("/^(none|nink|inpie|outpie|box|rbox|gauge|round)$/", $this->iconfile)) {
            $output .= "iconfile:" . WMUtility::jsEscape("::" . $this->iconfile);
        } else {
            $output .= "iconfile:" . WMUtility::jsEscape($this->iconfile);
        }

        return $output;
    }

    function asJS($type="Node", $prefix="N")
    {
        return parent::asJS($type, $prefix);
    }

    public function getValue($name)
    {
        wm_debug("Fetching %s from %s\n", $name, $this);
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        throw new WMException("NoSuchProperty");
    }
}
// vim:ts=4:sw=4:
