#!/usr/bin/env python3
"""
Expense Tracker CLI

Commands
- add:      Add a new expense
- list:     List expenses, optionally filtered
- summary:  Show totals grouped by category, day, or month
- export:   Export expenses to CSV or JSON

No third-party dependencies. Data persisted in expenses.csv.
"""
from __future__ import annotations

import argparse
import csv
import json
from collections import defaultdict
from dataclasses import dataclass, asdict
from datetime import date, datetime
from decimal import Decimal, InvalidOperation, ROUND_HALF_UP
from pathlib import Path
from typing import Dict, Iterable, List, Optional

CSV_FILE = Path(__file__).with_name("expenses.csv")
CSV_HEADERS = ["id", "date", "amount", "category", "note"]


@dataclass
class Expense:
    expense_id: int
    expense_date: date
    amount: Decimal
    category: str
    note: str

    def to_csv_row(self) -> Dict[str, str]:
        return {
            "id": str(self.expense_id),
            "date": self.expense_date.isoformat(),
            "amount": f"{self.amount.quantize(Decimal('0.01'), rounding=ROUND_HALF_UP)}",
            "category": self.category,
            "note": self.note,
        }

    @staticmethod
    def from_csv_row(row: Dict[str, str]) -> "Expense":
        # Defensive parsing with validation
        try:
            expense_id = int(row.get("id", "0"))
        except ValueError:
            raise ValueError(f"Invalid id in CSV: {row.get('id')}")

        try:
            parsed_date = datetime.strptime(row.get("date", ""), "%Y-%m-%d").date()
        except Exception as exc:
            raise ValueError(f"Invalid date in CSV: {row.get('date')}") from exc

        try:
            amount = Decimal(row.get("amount", "0"))
        except InvalidOperation as exc:
            raise ValueError(f"Invalid amount in CSV: {row.get('amount')}") from exc

        category = (row.get("category") or "").strip()
        note = (row.get("note") or "").strip()

        return Expense(
            expense_id=expense_id,
            expense_date=parsed_date,
            amount=amount,
            category=category,
            note=note,
        )


def ensure_csv_exists(csv_path: Path) -> None:
    if not csv_path.exists():
        with csv_path.open("w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=CSV_HEADERS)
            writer.writeheader()


def read_expenses(csv_path: Path) -> List[Expense]:
    ensure_csv_exists(csv_path)
    expenses: List[Expense] = []
    with csv_path.open("r", newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            if not row:
                continue
            expenses.append(Expense.from_csv_row(row))
    return expenses


def get_next_id(expenses: Iterable[Expense]) -> int:
    max_id = 0
    for e in expenses:
        if e.expense_id > max_id:
            max_id = e.expense_id
    return max_id + 1


def parse_date(date_str: Optional[str]) -> date:
    if not date_str:
        return date.today()
    try:
        return datetime.strptime(date_str, "%Y-%m-%d").date()
    except Exception as exc:
        raise argparse.ArgumentTypeError(
            "Invalid date format. Use YYYY-MM-DD."
        ) from exc


def parse_amount(amount_str: str) -> Decimal:
    try:
        amount = Decimal(amount_str)
    except InvalidOperation as exc:
        raise argparse.ArgumentTypeError("Amount must be a number") from exc
    if amount <= Decimal("0"):
        raise argparse.ArgumentTypeError("Amount must be positive")
    return amount


def add_expense_command(args: argparse.Namespace) -> None:
    expenses = read_expenses(CSV_FILE)
    new_expense = Expense(
        expense_id=get_next_id(expenses),
        expense_date=parse_date(args.date),
        amount=parse_amount(args.amount),
        category=(args.category or "uncategorized").strip(),
        note=(args.note or "").strip(),
    )

    # Append to CSV
    ensure_csv_exists(CSV_FILE)
    with CSV_FILE.open("a", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=CSV_HEADERS)
        writer.writerow(new_expense.to_csv_row())

    print(
        f"Added expense #{new_expense.expense_id}: "
        f"{new_expense.expense_date.isoformat()} | "
        f"{new_expense.category} | "
        f"{new_expense.amount.quantize(Decimal('0.01'))} | "
        f"{new_expense.note}"
    )


def within_range(d: date, start: Optional[date], end: Optional[date]) -> bool:
    if start and d < start:
        return False
    if end and d > end:
        return False
    return True


def list_expenses_command(args: argparse.Namespace) -> None:
    expenses = read_expenses(CSV_FILE)

    start_date = parse_date(args.from_date) if args.from_date else None
    end_date = parse_date(args.to_date) if args.to_date else None
    category_filter = (args.category or "").strip().lower() or None

    filtered: List[Expense] = []
    for e in expenses:
        if category_filter and e.category.lower() != category_filter:
            continue
        if not within_range(e.expense_date, start_date, end_date):
            continue
        filtered.append(e)

    if not filtered:
        print("No expenses found.")
        return

    rows = []
    headers = ["ID", "Date", "Amount", "Category", "Note"]
    rows.append(headers)
    for e in filtered:
        rows.append([
            str(e.expense_id),
            e.expense_date.isoformat(),
            f"{e.amount.quantize(Decimal('0.01'))}",
            e.category,
            e.note,
        ])

    print(format_table(rows))


def format_table(rows: List[List[str]]) -> str:
    # Calculate column widths
    col_widths: List[int] = []
    for col_idx in range(len(rows[0])):
        width = max(len(str(row[col_idx])) for row in rows)
        col_widths.append(width)

    def format_row(row: List[str]) -> str:
        parts = []
        for idx, cell in enumerate(row):
            text = str(cell)
            if idx == 2:  # Amount column right-align
                parts.append(text.rjust(col_widths[idx]))
            else:
                parts.append(text.ljust(col_widths[idx]))
        return "  ".join(parts)

    # Build table with a separator after header
    lines = []
    lines.append(format_row(rows[0]))
    separator = "  ".join("-" * w for w in col_widths)
    lines.append(separator)
    for row in rows[1:]:
        lines.append(format_row(row))
    return "\n".join(lines)


def summary_command(args: argparse.Namespace) -> None:
    expenses = read_expenses(CSV_FILE)

    start_date = parse_date(args.from_date) if args.from_date else None
    end_date = parse_date(args.to_date) if args.to_date else None

    grouping = args.by
    totals: Dict[str, Decimal] = defaultdict(lambda: Decimal("0"))

    for e in expenses:
        if not within_range(e.expense_date, start_date, end_date):
            continue
        if grouping == "category":
            key = e.category or "uncategorized"
        elif grouping == "day":
            key = e.expense_date.isoformat()
        elif grouping == "month":
            key = e.expense_date.strftime("%Y-%m")
        else:
            key = "all"
        totals[key] += e.amount

    if not totals:
        print("No expenses found.")
        return

    rows = [[grouping.capitalize(), "Total"]]
    for key, total in sorted(totals.items()):
        rows.append([key, f"{total.quantize(Decimal('0.01'))}"])
    print(format_table(rows))


def export_command(args: argparse.Namespace) -> None:
    expenses = read_expenses(CSV_FILE)

    fmt = args.format
    output_path = Path(args.output) if args.output else (
        CSV_FILE.with_name("expenses_export.json") if fmt == "json" else CSV_FILE.with_name("expenses_export.csv")
    )

    if fmt == "json":
        serializable = [
            {
                "id": e.expense_id,
                "date": e.expense_date.isoformat(),
                "amount": float(e.amount),
                "category": e.category,
                "note": e.note,
            }
            for e in expenses
        ]
        output_path.write_text(json.dumps(serializable, indent=2, ensure_ascii=False), encoding="utf-8")
        print(f"Exported {len(expenses)} expenses to {output_path}")
    elif fmt == "csv":
        with output_path.open("w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=CSV_HEADERS)
            writer.writeheader()
            for e in expenses:
                writer.writerow(e.to_csv_row())
        print(f"Exported {len(expenses)} expenses to {output_path}")
    else:
        raise ValueError("Unsupported export format")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="expense-tracker",
        description="Simple Expense Tracker CLI (CSV-based)",
    )
    subparsers = parser.add_subparsers(dest="command", required=True)

    # add
    p_add = subparsers.add_parser("add", help="Add a new expense")
    p_add.add_argument("--amount", required=True, help="Amount, e.g. 12.50")
    p_add.add_argument("--category", required=True, help="Category, e.g. food")
    p_add.add_argument("--date", help="Date YYYY-MM-DD (default: today)")
    p_add.add_argument("--note", help="Optional note", default="")
    p_add.set_defaults(func=add_expense_command)

    # list
    p_list = subparsers.add_parser("list", help="List expenses")
    p_list.add_argument("--category", help="Filter by category")
    p_list.add_argument("--from", dest="from_date", help="Start date YYYY-MM-DD")
    p_list.add_argument("--to", dest="to_date", help="End date YYYY-MM-DD")
    p_list.set_defaults(func=list_expenses_command)

    # summary
    p_sum = subparsers.add_parser("summary", help="Summarize expenses")
    p_sum.add_argument("--by", choices=["category", "day", "month"], default="category")
    p_sum.add_argument("--from", dest="from_date", help="Start date YYYY-MM-DD")
    p_sum.add_argument("--to", dest="to_date", help="End date YYYY-MM-DD")
    p_sum.set_defaults(func=summary_command)

    # export
    p_export = subparsers.add_parser("export", help="Export expenses to CSV or JSON")
    p_export.add_argument("--format", choices=["csv", "json"], required=True)
    p_export.add_argument("--output", help="Output file path")
    p_export.set_defaults(func=export_command)

    return parser


def main(argv: Optional[List[str]] = None) -> None:
    parser = build_parser()
    args = parser.parse_args(argv)
    args.func(args)


if __name__ == "__main__":
    main()
