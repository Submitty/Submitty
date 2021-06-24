<?php

declare(strict_types=1);

namespace app\entities\db;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="columns", schema="information_schema")
 */
class Column {
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
    protected $table_name;

  /**
   * @ORM\Id
   * @ORM\Column(name="column_name",type="string")
   * @var string
   */
    protected $name;

  /**
   * @ORM\Column(name="ordinal_position",type="integer")
   * @var int
   */
    protected $position;

  /**
   * @ORM\Column(name="data_type",type="string")
   * @var string
   */
    protected $type;

  /**
   * @ORM\ManyToOne(targetEntity="\app\entities\db\Table",inversedBy="columns")
   * @ORM\JoinColumns({
   * @ORM\JoinColumn(name="table_catalog", referencedColumnName="table_catalog"),
   * @ORM\JoinColumn(name="table_schema", referencedColumnName="table_schema"),
   * @ORM\JoinColumn(name="table_name", referencedColumnName="table_name")
   * })
   * @var Table
   */
    protected $table;

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
