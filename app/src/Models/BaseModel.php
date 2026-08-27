<?php
namespace Models;

use Database\Connection;
use PDO;

abstract class BaseModel
{
    /** Nome da tabela no SQL Server (dbo.<table>). */
    protected static string $table = '';

    /** Colunas que podem ser preenchidas em massa via fill(). */
    protected static array $fillable = [];

    /**
     * Busca um registro pelo PK (id).
     * Retorna instância do model ou null.
     */
    public static function find(int $id): ?static
    {
        $pdo  = Connection::get();
        $tbl  = static::$table;
        $stmt = $pdo->prepare("SELECT * FROM dbo.{$tbl} WHERE id = ?");
        $stmt->execute([$id]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? static::fromRow($row) : null;
    }

    /**
     * Retorna todos os registros da tabela.
     * Opcional: array de condições ['coluna' => valor] (AND exato).
     *
     * @return static[]
     */
    public static function findAll(array $where = [], string $orderBy = ''): array
    {
        $pdo  = Connection::get();
        $tbl  = static::$table;
        $sql  = "SELECT * FROM dbo.{$tbl}";
        $params = [];

        if ($where) {
            $clauses = [];
            foreach ($where as $col => $val) {
                $clauses[] = "{$col} = ?";
                $params[]  = $val;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(
            static fn(array $row) => static::fromRow($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * INSERT ou UPDATE dependendo de $this->id estar preenchido.
     * Retorna o id do registro (novo ou existente).
     */
    public function save(): int
    {
        $pdo  = Connection::get();
        $tbl  = static::$table;
        $data = $this->toArray();

        if (empty($data['id'])) {
            // INSERT
            unset($data['id']);
            $data = array_filter($data, static fn($value) => $value !== null);
            $cols   = array_keys($data);
            $places = array_fill(0, count($cols), '?');
            $sql    = sprintf(
                'INSERT INTO dbo.%s (%s) VALUES (%s); SELECT SCOPE_IDENTITY() AS id',
                $tbl,
                implode(', ', $cols),
                implode(', ', $places)
            );
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            $stmt->nextRowset();
            $this->id = (int) $stmt->fetchColumn();
        } else {
            // UPDATE
            $id = $data['id'];
            unset($data['id']);
            $sets = array_map(fn($c) => "{$c} = ?", array_keys($data));
            $sql  = sprintf(
                'UPDATE dbo.%s SET %s WHERE id = ?',
                $tbl,
                implode(', ', $sets)
            );
            $vals   = array_values($data);
            $vals[] = $id;
            $pdo->prepare($sql)->execute($vals);
        }

        return $this->id;
    }

    /**
     * DELETE pelo id da instância.
     */
    public function delete(): void
    {
        $pdo = Connection::get();
        $tbl = static::$table;
        $pdo->prepare("DELETE FROM dbo.{$tbl} WHERE id = ?")
            ->execute([$this->id]);
    }

    // Helpers
    /**
     * Preenche propriedades da instância a partir de um array associativo.
     * Apenas colunas em $fillable (ou todas se $fillable estiver vazio).
     */
    public function fill(array $data): static
    {
        $allowed = static::$fillable ?: array_keys($data);
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $this->$col = $data[$col];
            }
        }
        return $this;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Cria uma instância da subclasse a partir de uma linha do PDO.
     */
    public static function fromRow(array $row): static
    {
        $instance = new static();
        foreach ($row as $col => $val) {
            $instance->$col = $val;
        }
        return $instance;
    }
}


