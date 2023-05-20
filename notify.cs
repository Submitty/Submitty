using System;
using System.IO;

class Program
{
    static void Main()
    {
        // Set the directory path to monitor recursively
        string directoryPath = @"/var/local/submitty/courses";

        // Create a new FileSystemWatcher instance
        FileSystemWatcher watcher = new FileSystemWatcher(directoryPath);

        // Set the options to monitor subdirectories and all events
        watcher.IncludeSubdirectories = true;
        watcher.NotifyFilter = NotifyFilters.DirectoryName | NotifyFilters.FileName | NotifyFilters.Attributes |
                               NotifyFilters.Size | NotifyFilters.LastWrite | NotifyFilters.Security;

        // Subscribe to the events
        watcher.Created += OnFileEvent;
        watcher.Deleted += OnFileEvent;
        watcher.Changed += OnFileEvent;
        watcher.Renamed += OnFileRenamed;

        // Start monitoring
        watcher.EnableRaisingEvents = true;

        Console.WriteLine("Press Enter to stop monitoring.");
        Console.ReadLine();

        // Stop monitoring
        watcher.EnableRaisingEvents = false;
    }

    static void OnFileEvent(object sender, FileSystemEventArgs e)
    {
        Console.WriteLine("File " + e.ChangeType + ": " + e.FullPath);
    }

    static void OnFileRenamed(object sender, RenamedEventArgs e)
    {
        Console.WriteLine("File renamed: " + e.OldFullPath + " to " + e.FullPath);
    }
}
