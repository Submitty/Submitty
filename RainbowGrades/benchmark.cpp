#include <cassert>
#include <iostream>

#include "benchmark.h"

std::vector<std::pair<std::string,Benchmark> > DISPLAY_BENCHMARKS;
int Benchmark::next_benchmark_position = 0;

int FIND_BENCHMARK(const std::string& s) {
  for (std::size_t i = 0; i < DISPLAY_BENCHMARKS.size(); i++) {
    if (DISPLAY_BENCHMARKS[i].first == s) return i;
  }
  return -1;
}

Benchmark::Benchmark(const std::string& s) {
  assert (FIND_BENCHMARK(s) == -1);
  assert (s == "extracredit" ||
          s == "perfect" ||
          s == "average" ||
          s == "stddev" ||
          s == "lowest_a-" ||
          s == "lowest_b-" ||
          s == "lowest_c-" ||
          s == "lowest_d" ||
          s == "failing");
  name = s;
  visible = false;
  percentage = -1;
  color = "ffffff";
  position = -1;
  DISPLAY_BENCHMARKS.push_back(std::make_pair(s,*this));
}

Benchmark& Benchmark::GetBenchmark(const std::string& s) {
  assert (s == "extracredit" ||
          s == "perfect" ||
          s == "average" ||
          s == "stddev" ||
          s == "lowest_a-" ||
          s == "lowest_b-" ||
          s == "lowest_c-" ||
          s == "lowest_d" ||
          s == "failing");
  int i = FIND_BENCHMARK(s);
  if (i == -1) {
    Benchmark b(s);
    i = FIND_BENCHMARK(s);
  }
  assert (i != -1);
  return DISPLAY_BENCHMARKS[i].second;
}


void DisplayBenchmark(const std::string& s) {
  Benchmark& b = Benchmark::GetBenchmark(s);
  b.visible=true;
  b.position = Benchmark::next_benchmark_position;
  Benchmark::next_benchmark_position++;
}

int NumVisibleBenchmarks() {
  return Benchmark::next_benchmark_position;
}

int WhichVisibleBenchmark(const std::string& s) {
  for (std::size_t i = 0; i < DISPLAY_BENCHMARKS.size(); i++) {
    if (DISPLAY_BENCHMARKS[i].first == s) { return DISPLAY_BENCHMARKS[i].second.position; }
  }
  return -1;
}

void SetBenchmarkPercentage(const std::string& s, float v) {
  assert (s == "lowest_a-" ||
          s == "lowest_b-" ||
          s == "lowest_c-" ||
          s == "lowest_d");
  Benchmark& b = Benchmark::GetBenchmark(s);
  assert (v >= 0.0 && v <= 1.0);
  b.percentage = v;
}


float GetBenchmarkPercentage(const std::string& s) {
  assert (s == "lowest_a-" ||
          s == "lowest_b-" ||
          s == "lowest_c-" ||
          s == "lowest_d");
  Benchmark& b = Benchmark::GetBenchmark(s);
  return b.percentage;
}



void SetBenchmarkColor(const std::string& s, const std::string& c) {
  assert (s == "extracredit" ||
          s == "perfect" ||
          s == "lowest_a-" ||
          s == "lowest_b-" ||
          s == "lowest_c-" ||
          s == "lowest_d" ||
          s == "failing");
  Benchmark& b = Benchmark::GetBenchmark(s);
  assert (c.size() == 6);
  b.color = c;
}


const std::string& GetBenchmarkColor(const std::string& s) {
  assert (s == "extracredit" ||
          s == "perfect" ||
          s == "lowest_a-" ||
          s == "lowest_b-" ||
          s == "lowest_c-" ||
          s == "lowest_d" ||
          s == "failing");
  Benchmark& b = Benchmark::GetBenchmark(s);
  return b.color;
}
