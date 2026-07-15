import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  StyleSheet,
  SafeAreaView,
} from 'react-native';
import PostCard from '../components/PostCard';
import { getFeed, searchPosts, logInteraction } from '../api/client';

export default function FeedScreen() {
  const [posts, setPosts] = useState([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState(null);

  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState(null); // null = not searching
  const [searching, setSearching] = useState(false);

  const loadFeed = useCallback(async (pageToLoad = 1) => {
    try {
      if (pageToLoad === 1) setLoading(true);
      else setLoadingMore(true);
      setError(null);

      const response = await getFeed(pageToLoad);
      const newPosts = response.data || [];

      setPosts((prev) => (pageToLoad === 1 ? newPosts : [...prev, ...newPosts]));
      setHasMore(newPosts.length > 0 && pageToLoad * 20 < (response.meta?.total ?? 0));
      setPage(pageToLoad);
    } catch (err) {
      setError('Could not load the feed. Pull down or tap retry.');
    } finally {
      setLoading(false);
      setLoadingMore(false);
    }
  }, []);

  useEffect(() => {
    loadFeed(1);
  }, [loadFeed]);

  const handleLoadMore = () => {
    if (!loadingMore && hasMore && !searchResults) {
      loadFeed(page + 1);
    }
  };

  const handleSearchChange = async (text) => {
    setSearchQuery(text);
    if (text.trim().length === 0) {
      setSearchResults(null);
      return;
    }

    try {
      setSearching(true);
      const response = await searchPosts(text.trim());
      setSearchResults(response.data || []);
    } catch (err) {
      setSearchResults([]);
    } finally {
      setSearching(false);
    }
  };

  const handleReact = (postId) => {
    logInteraction(postId, 'reaction').catch(() => {
      // Fail silently in UI — reaction still shows locally, log for debugging
      console.warn('Failed to log reaction for post', postId);
    });
  };

  const dataToShow = searchResults !== null ? searchResults : posts;

  const renderEmpty = () => {
    if (loading || searching) return null;
    return (
      <View style={styles.centerState}>
        <Text style={styles.emptyText}>
          {searchResults !== null ? 'No posts match your search.' : 'No posts yet. Check back soon.'}
        </Text>
      </View>
    );
  };

  const renderFooter = () => {
    if (!loadingMore) return null;
    return (
      <View style={styles.footerLoading}>
        <ActivityIndicator size="small" color="#E07A5F" />
      </View>
    );
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.centerState}>
          <ActivityIndicator size="large" color="#E07A5F" />
        </View>
      </SafeAreaView>
    );
  }

  if (error && posts.length === 0) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.centerState}>
          <Text style={styles.errorText}>{error}</Text>
          <TouchableOpacity style={styles.retryButton} onPress={() => loadFeed(1)}>
            <Text style={styles.retryButtonText}>Retry</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <View style={styles.headerBar}>
        <Text style={styles.title}>Real Connections</Text>
      </View>

      <View style={styles.searchContainer}>
        <TextInput
          style={styles.searchInput}
          placeholder="Search posts by topic, mood, memory..."
          placeholderTextColor="#B0B0B8"
          value={searchQuery}
          onChangeText={handleSearchChange}
        />
        {searching && <ActivityIndicator size="small" color="#E07A5F" style={styles.searchSpinner} />}
      </View>

      <FlatList
        data={dataToShow}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => <PostCard post={item} onReact={handleReact} />}
        onEndReached={handleLoadMore}
        onEndReachedThreshold={0.4}
        ListEmptyComponent={renderEmpty}
        ListFooterComponent={renderFooter}
        contentContainerStyle={dataToShow.length === 0 ? styles.emptyContainer : styles.listContainer}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#F7F5F2',
  },
  headerBar: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 4,
  },
  title: {
    fontSize: 24,
    fontWeight: '700',
    color: '#2B2B33',
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 16,
    marginVertical: 10,
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    paddingHorizontal: 14,
    height: 44,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.04,
    shadowRadius: 4,
    elevation: 1,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    color: '#2B2B33',
  },
  searchSpinner: {
    marginLeft: 8,
  },
  listContainer: {
    paddingBottom: 20,
  },
  emptyContainer: {
    flexGrow: 1,
    justifyContent: 'center',
  },
  centerState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 32,
  },
  emptyText: {
    fontSize: 14,
    color: '#9A9AA5',
    textAlign: 'center',
  },
  errorText: {
    fontSize: 14,
    color: '#C0392B',
    textAlign: 'center',
    marginBottom: 16,
  },
  retryButton: {
    backgroundColor: '#E07A5F',
    paddingHorizontal: 24,
    paddingVertical: 10,
    borderRadius: 10,
  },
  retryButtonText: {
    color: '#FFFFFF',
    fontWeight: '600',
    fontSize: 14,
  },
  footerLoading: {
    paddingVertical: 20,
  },
});
