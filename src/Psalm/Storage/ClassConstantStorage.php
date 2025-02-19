<?php
namespace Psalm\Storage;

use Psalm\CodeLocation;
use Psalm\Internal\Analyzer\ClassLikeAnalyzer;
use Psalm\Internal\Scanner\UnresolvedConstantComponent;
use Psalm\Type;

class ClassConstantStorage
{
    /**
     * @var ?Type\Union
     */
    public $type;

    /**
     * @var ClassLikeAnalyzer::VISIBILITY_*
     */
    public $visibility = ClassLikeAnalyzer::VISIBILITY_PUBLIC;

    /**
     * @var ?CodeLocation
     */
    public $location;

    /**
     * @var ?CodeLocation
     */
    public $stmt_location;

    /**
     * @var ?UnresolvedConstantComponent
     */
    public $unresolved_node;

    /**
     * @var bool
     */
    public $deprecated = false;

    /**
     * @var list<AttributeStorage>
     */
    public $attributes = [];

    /**
     * @var ?string
     */
    public $description;

    /**
     * @param ClassLikeAnalyzer::VISIBILITY_* $visibility
     */
    public function __construct(?Type\Union $type, int $visibility, ?CodeLocation $location)
    {
        $this->visibility = $visibility;
        $this->location = $location;
        $this->type = $type;
    }
}
