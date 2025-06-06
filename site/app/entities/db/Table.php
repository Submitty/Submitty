<?php

declare(strict_types=1);

namespace app\entities\db;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "tables", schema: "information_schema")]
class Table {
    #[ORM\Id]
    #[ORM\Column(name: "table_catalog", type: Types::STRING)]
    protected string $database;

    #[ORM\Id]
    #[ORM\Column(name: "table_schema", type: Types::STRING)]
    protected string $schema;

    #[ORM\Id]
    #[ORM\Column(name: "table_name", type: Types::STRING)]
    protected string $name;

    /**
     * @var Collection<Column>
     */
    #[ORM\OneToMany(mappedBy: "table", targetEntity: Column::class)]
    #[ORM\JoinColumn(name: "table_catalog", referencedColumnName: "table_catalog")]
    #[ORM\JoinColumn(name: "table_schema", referencedColumnName: "table_schema")]
    #[ORM\JoinColumn(name: "table_name", referencedColumnName: "table_name")]
    #[ORM\OrderBy(["position" => "ASC"])]
    protected Collection $columns;

    public function __construct() {
        $this->columns = new Collection();
        throw new \RuntimeException("Cannot create new information_schema.table");
    }

    public function getName(): string {
        return $this->name;
    }

    /**
     * @return Collection<Column>
     */
    public function getColumns(): Collection {
        return $this->columns;
    }
}
