"""Quick validation script for yfinance and SEC EDGAR tools.

Run with:
    uvx --with yfinance --with httpx python scripts/test_financial_tools.py
"""

import asyncio
import sys

sys.path.insert(0, ".")


async def test_yfinance(ticker: str):
    from app.agents.tools.yfinance_tool import fetch_yfinance_data

    print(f"\n{'=' * 60}")
    print(f"yfinance → {ticker}")
    print("=" * 60)
    data = await fetch_yfinance_data(ticker)
    if data:
        for k, v in data.items():
            print(f"  {k:<20} {v}")
    else:
        print("  ⚠ No data returned")
    return data


async def test_sec_edgar(company_name: str, ticker: str | None = None):
    from app.agents.tools.sec_edgar_tool import fetch_sec_edgar_data

    print(f"\n{'=' * 60}")
    print(f"SEC EDGAR → {company_name} (ticker={ticker})")
    print("=" * 60)
    data = await fetch_sec_edgar_data(company_name, ticker)
    if data:
        for k, v in data.items():
            if isinstance(v, dict):
                print(f"  {k:<20} {v.get('value', '')}  [{v.get('period', '')}]")
            else:
                print(f"  {k:<20} {v}")
    else:
        print("  ⚠ No data returned")
    return data


async def main():
    print("\n🔍 Testing financial data tools\n")

    # --- yfinance tests ---
    await test_yfinance("AAPL")
    await test_yfinance("MSFT")
    await test_yfinance("MC.PA")  # Non-US: LVMH on Euronext

    # --- SEC EDGAR tests ---
    await test_sec_edgar("Apple Inc.", "AAPL")
    await test_sec_edgar("Microsoft", "MSFT")
    await test_sec_edgar("Unknown Private Co", None)  # Should return {}

    print(f"\n{'=' * 60}")
    print("Done.")


if __name__ == "__main__":
    asyncio.run(main())
