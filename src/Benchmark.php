<?php
namespace Fzb;

class Benchmark
{
	public string $name;
	private int $time_start;
	private int $time_end;
	public float $time_total;

	private static $instances = array();

	function __construct($name)
	{
		$this->name = $name;
		
		array_push(self::$instances, $this);
	}

	public function start(): void
	{
		$this->time_start = hrtime(true);
	}

	public function end(): void
	{
		$this->time_end = hrtime(true);
		$this->time_total = ($this->time_end - $this->time_start) / 1e+9;
	}

	public static function show(): void
	{
		?>
		<h5>Benchmarks</h5>
		<table>
			<thead>
				<tr>
					<th>operation</u></b></th>
					<th>time elapsed</b></u></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach (self::$instances as $bench): ?>
				<tr><td><?= $bench->name ?></pre></td><td><?= number_format($bench->time_total, 6) ?> sec</pre></td></tr>
				<?php endforeach ?>
			</tbody>
		</table>
		<?php
	}
}
