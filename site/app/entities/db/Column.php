<?php

declare(strict_types=1);

namespace app\entities\db;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "columns", schema: "information_schema")]
class Column {
    #[ORM\Id]
    #[ORM\Column(name: "table_catalog", type: Types::STRING)]
    protected string $database;

    #[ORM\Id]
    #[ORM\Column(name: "table_schema", type: Types::STRING)]
    protected string $schema;

    #[ORM\Id]
    #[ORM\Column(name: "table_name", type: Types::STRING)]
    protected string $table_name;

    #[ORM\Id]
    #[ORM\Column(name: "column_name", type: Types::STRING)]
    protected string $name;

    #[ORM\Column(name: "ordinal_position", type: Types::INTEGER)]
    protected int $position;

    #[ORM\Column(name: "data_type", type: Types::STRING)]
    protected string $type;

    #[ORM\ManyToOne(targetEntity: Table::class, inversedBy: "columns")]
    #[ORM\JoinColumn(name: "table_catalog", referencedColumnName: "table_catalog")]
    #[ORM\JoinColumn(name: "table_schema", referencedColumnName: "table_schema")]
    #[ORM\JoinColumn(name: "table_name", referencedColumnName: "table_name")]
    protected ?Table $table;

    public function __construct() {
        throw new \RuntimeException("Cannot create new information_schema.column");
    }

    public function getName(): string {
        return $this->name;
    }

    public function getType(): string {
        return $this->type;
    }
}
