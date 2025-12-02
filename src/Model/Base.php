<?php
/**
 * Class Base
 * 
 * Base model class providing common database fields for all models.
 * Includes auto-incrementing primary key and timestamp tracking for record creation and updates.
 * Extend this class to inherit standard id, created_at, and updated_at columns.
 * 
 * Usage: class MyModel extends Fzb\Model\Base { ... }
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

namespace Fzb\Model;

use Fzb\Model\Model;
use Fzb\Model\Column;
use Fzb\Model\Table;
use Fzb\Model\Type;
use Fzb\Model\Time;
use DateTime;

#[Table]
class Base extends Model
{
    /**
     * @var int Auto-incrementing primary key
     */
    #[Column(type: Type::INT, unsigned: true, null: false, auto_increment: true)]
    #[PrimaryKey]
    public int $id;

    /**
     * @var DateTime Timestamp when the record was created
     */
    #[Column(type: Type::DATETIME, null: false, default: Time::CURRENT_TIMESTAMP)]
    public DateTime $created_at;

    /**
     * @var DateTime Timestamp when the record was last updated
     */
    #[Column(type: Type::DATETIME, null: false, default: Time::CURRENT_TIMESTAMP, on_update: Time::CURRENT_TIMESTAMP)]
    public DateTime $updated_at;
}