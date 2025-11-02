<?php

namespace Fzb\Model;

use Fzb\Model;
use Fzb\Model\Column;
use Fzb\Model\Table;
use Fzb\Model\Type;
use DateTime;

#[Table]
class Base extends Model
{
    #[Column(type: Type::INT, unsigned: true, null: false, auto_increment: true)]
    #[PrimaryKey]
    public int $id;

    #[Column(type: Type::DATETIME)]
    public DateTime $created_at;

    #[Column(type: Type::DATETIME)]
    public DateTime $updated_at;
}