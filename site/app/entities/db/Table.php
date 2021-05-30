<?php

namespace app\entities\db;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="tables", schema="information_schema")
 */
class Table {
  /**
   * @ORM\Id
   * @ORM\Column(name="table_catalog",type="string")
   */
  protected $database;
  /**
   * @ORM\Id
   * @ORM\Column(name="table_schema",type="string")
   */
  protected $schema;
  /**
   * @ORM\Id
   * @ORM\Column(name="table_name",type="string")
   */
  protected $name;

  /**
   * @ORM\OneToMany(targetEntity="\app\entities\db\Column",mappedBy="table")
   * @ORM\JoinColumns({
   *    @ORM\JoinColumn(name="table_catalog", referencedColumnName="table_catalog"),
   *    @ORM\JoinColumn(name="table_schema", referencedColumnName="table_schema"),
   *    @ORM\JoinColumn(name="table_name", referencedColumnName="table_name")
   * })
   * @ORM\OrderBy({"position" = "ASC"})
   */
  protected $columns;

  public function __construct() {
    $this->columns = new ArrayCollection();
  }

  public function getName() {
    return $this->name;
  }

  /**
   * @return ArrayCollection<Column>
   */
  public function getColumns() {
    return $this->columns;
  }
}
