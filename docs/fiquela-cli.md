# FiQueLa CLI

FiQueLa CLI is a command-line tool that allows users to execute SQL-like queries on structured data files, including
XLS, CSV, JSON, XML, YAML, and NEON.

**table of contents**:

* _1_ - [Installation](#installation)
* _2_ - [Usage](#usage)
* _3_ - [Interactive Mode](#interactive-mode)
* _4_ - [Output Format](#output-format)
* _5_ - [Conclusion](#conclusion)

## Installation

If you want to make `fiquela-cli` globally accessible, create a symbolic link:

```bash
ln -s $(pwd)/bin/fiquela-cli /usr/local/bin/fiquela-cli
chmod +x /usr/local/bin/fiquela-cli
```

## Usage

### Running the Command

```bash
fiquela-cli [options] [query]
```

### Options

| Parameter          | Shortcut | Description                            |
|--------------------|----------|----------------------------------------|
| `--preview`        | `-p`     | Show file contents                     |
| `--url`            | `-u`     | URL of the data file                   |
| `--file`           | `-f`     | Path to the data file                  |
| `--file-type`      | `-t`     | File type (csv, xml, json, yaml, neon) |
| `--file-delimiter` | `-d`     | CSV file delimiter (default `,`)       |
| `--file-encoding`  | `-e`     | File encoding (default `utf-8`)        |
| `--memory-limit`   | `-m`     | Set memory limit (e.g. `128M`)         |
| `--help`           | `-h`     | Show the help for fiquela-cli          |

### Examples

#### 1. Preview File Contents

```bash
fiquela-cli --preview --file=data.csv
```

#### 2. Run Query on CSV File

```bash
fiquela-cli --file=data.csv --file-type=csv "SELECT name, age FROM users WHERE age > 30 ORDER BY age DESC;"
```

#### 3. Interactive Mode

```bash
fiquela-cli
```

In interactive mode, you can enter SQL-like queries and get results in a table format. To exit, type `exit` or press `Ctrl+C`.

## Interactive Mode

Interactive mode supports query history and pagination for results.

```bash
fiquela-cli
```

```text
Welcome to FiQueLa interactive mode. Commands end with ;.

Memory limit: 128M

Type 'exit' or 'Ctrl-c' to quit.
fql>
```

### Controls:

- `[Enter]` or `n` – Next page
- `b` – Previous page
- `l` – Last page
- `f` – First page
- `j` – Export to JSON (only available in interactive mode)
- `exit` or `q` – Quit

### Example

```text
fql> SELECT channel, SUM(budget) AS total_budget, SUM(revenue) AS total_revenue FROM [json](./examples/data/marketing-campaigns.json).* GROUP BY channel;
+-----------+---- Page 1/1 +---------------+
| channel   | total_budget | total_revenue |
+-----------+--------------+---------------+
| organic   | 12060966.38  | 133104578.3   |
| promotion | 13495668.71  | 136182504.28  |
| paid      | 11880346.61  | 127080318.3   |
| referral  | 12112599.21  | 121262174.84  |
+-------- Showing 1-4 from 4 rows ---------+
0.0882 sec, memory 4.7502 (emalloc), memory (peak) 4.8851 (emalloc)
```

### Export (Interactive Mode Only)

In interactive mode, users can export query results to JSON or CSV by pressing `:e`. The tool will prompt for
a file name and save the output in chosen format.
This feature is currently not yet available as a standalone command-line option.

## Output Format

Query results are displayed in a tabular format with performance metrics:

```bash
0.0882 sec, memory 4.7502 (emalloc), memory (peak) 4.8851 (emalloc)
```

## Conclusion

FiQueLa CLI is a powerful tool for querying structured data files using SQL-like commands.
It provides an interactive environment, efficient memory management, and result export functionality.

## Next steps
- [Opening Files](opening-files.md)
- [Fluent API](fluent-api.md)
- [File Query Language](file-query-language.md)
- [Fetching Data](fetching-data.md)
- [Query Life Cycle](query-life-cycle.md)
- FiQueLa CLI
- [Query Inspection and Benchmarking](query-inspection-and-benchmarking.md)

or go back to [README.md](../README.md).
