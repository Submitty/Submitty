<?php

declare(strict_types=1);

namespace app\entities\db;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="tables", schema="information_schema")
 */
class Table {
  /**
   * @ORM\Id
   * @ORM\Column(name="table_catalog",type="string")
   * @var string
   */
    protected $database;
  /**
   * @ORM\Id
   * @ORM\Column(name="table_schema",type="string")
   * @var string
   */
    protected $schema;

  /**
   * @ORM\Id
   * @ORM\Column(name="table_name",type="string")
   * @var string
   */
    protected $name;

  /**
   * @ORM\OneToMany(targetEntity="\app\entities\db\Column",mappedBy="table")
   * @ORM\JoinColumns({
   * @ORM\JoinColumn(name="table_catalog", referencedColumnName="table_catalog"),
   * @ORM\JoinColumn(name="table_schema", referencedColumnName="table_schema"),
   * @ORM\JoinColumn(name="table_name", referencedColumnName="table_name")
   * })
   * @ORM\OrderBy({"position" = "ASC"})
   * @var Collection<Column>
   */
    protected $columns;

    public function __construct() {
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
