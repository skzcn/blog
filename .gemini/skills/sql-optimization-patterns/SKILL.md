---
name: sql-optimization-patterns SQL 性能优化模式
description: 高级 SQL 优化模式。专注于提升数据库查询效率，包括索引策略深度解析、N+1 查询问题解决、执行计划（EXPLAIN）分析、分页优化、复杂子查询重构以及大规模数据批处理技巧。
---

# SQL Optimization Patterns

Transform slow database queries into lightning-fast operations through systematic optimization, proper indexing, and query plan analysis.

## When to Use This Skill

- Debugging slow-running queries
- Designing performant database schemas
- Optimizing application response times
- Reducing database load and costs
- Improving scalability for growing datasets
- Analyzing EXPLAIN query plans
- Implementing efficient indexes
- Resolving N+1 query problems

## Core Concepts

### 1. Query Execution Plans (EXPLAIN)

Understanding EXPLAIN output is fundamental to optimization.

Key Metrics to Watch:

- **Seq Scan**: Full table scan (usually slow for large tables)
- **Index Scan**: Using index (good)
- **Index Only Scan**: Using index without touching table (best)
- **Cost**: Estimated query cost (lower is better)
- **Actual Time**: Real execution time (using EXPLAIN ANALYZE)

### 2. Index Strategies

- **B-Tree**: Default, good for equality and range queries.
- **Composite Index**: Multi-column index; column order matters.
- **Partial Index**: Index a subset of rows (e.g., `WHERE status = 'active'`).
- **Covering Index**: Include additional columns in the index to avoid table lookups.

### 3. Query Optimization Patterns

- **Avoid SELECT \***: Fetch only required columns.
- **Efficient WHERE Clause**: Avoid functions on indexed columns (use functional indexes instead).
- **Filter Before JOIN**: Apply filters early to reduce the dataset size for joins.

## Optimization Patterns

### Pattern 1: Eliminate N+1 Queries

**Problem**: Executing a query inside a loop for each parent record.
**Solution**: Use JOINs or batch loading (`WHERE id IN (...)`).

### Pattern 2: Optimize Pagination

**Bad**: `OFFSET` on large tables (becomes slow as offset increases).
**Good**: **Cursor-Based Pagination** using the last seen ID/timestamp.

### Pattern 3: Aggregate Efficiently

- Use estimates for approximate counts on large tables.
- Filter data before performing `GROUP BY`.
- Use covering indexes for aggregate columns.

### Pattern 4: Subquery Optimization

- Transform correlated subqueries into `JOIN`s with aggregation.
- Use **CTEs (Common Table Expressions)** for clarity in complex logic.

### Pattern 5: Batch Operations

- Use multi-row `INSERT` statements.
- Use `IN` clause for batch `UPDATE`s or temporary tables for very large sets.

## Best Practices

1. **Index Selectively**: Every index adds overhead to writes.
2. **Monitor Slow Logs**: Regularly check database slow query logs.
3. **Keep Stats Updated**: Run `ANALYZE` to help the optimizer.
4. **Connection Pooling**: Reuse connections to reduce latency.
5. **Normalize Thoughtfully**: Balance normalization against performance needs.
