# Partial Press

This is the source code for my bias-aware news aggregator, `https://partial.press`. It's a simple cron script that fetches news headlines from NewsAPI.org, does a bit of error checking, and then saves the results to `public_html/news.json`. The headlines are then read in from index.html via an AJAX call. Very straightforward, but illustrates how you can build something cute in a very simple manner. Free to use as you like!
