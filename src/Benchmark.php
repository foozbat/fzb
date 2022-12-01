<?php
/**
 * Class Fzb\Benchmark
 * 
 * Simple class to benchmark any desired segment of code.
 * Keeps track of all Fzb\Benchmark instances for printing of benchmark stats.
 * 
 * usage: Instantiate with $bm = new Fzb\Benchmark();
 * 
 * @author  Aaron Bishop (github.com/foozbat)
 */

namespace Fzb;

class Benchmark
{
	public string $name;
	private int $time_start;
	private int $time_end;
	public float $time_total;

	private static $instances = array();

	/**
	 * Constructor
	 *
	 * @param string $name Text identifier for benchmark instance
	 */
	function __construct(string $name)
	{
		$this->name = $name;
		
		array_push(self::$instances, $this);
	}

	/**
	 * Start Benchmarking
	 *
	 * @return void
	 */
	public function start(): void
	{
		$this->time_start = hrtime(true);
	}

	/**
	 * End Benchmarking
	 *
	 * @return void
	 */
	public function end(): void
	{
		$this->time_end = hrtime(true);
		$this->time_total = ($this->time_end - $this->time_start) / 1e+9;
	}

	/**
	 * Print out Benchmark stats
	 * 
	 * @todo refactor out embedded HTML
	 */
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
