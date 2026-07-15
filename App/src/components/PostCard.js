import React, { useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { timeAgo } from '../utils/timeAgo';

const AVATAR_COLORS = ['#E07A5F', '#3D405B', '#81B29A', '#F2CC8F', '#5B8C85'];

function colorForId(id) {
  return AVATAR_COLORS[id % AVATAR_COLORS.length];
}

function initialsFor(name) {
  if (!name) return '?';
  return name.trim().charAt(0).toUpperCase();
}

export default function PostCard({ post, onReact }) {
  const [reacted, setReacted] = useState(false);
  const authorName = post.author?.name || `User ${post.author_id ?? ''}`;

  const handleReact = () => {
    setReacted((prev) => !prev);
    onReact?.(post.id);
  };

  return (
    <View style={styles.card}>
      <View style={styles.header}>
        <View style={[styles.avatar, { backgroundColor: colorForId(post.author_id ?? post.id) }]}>
          <Text style={styles.avatarText}>{initialsFor(authorName)}</Text>
        </View>
        <View style={styles.headerText}>
          <Text style={styles.username}>{authorName}</Text>
          <Text style={styles.timestamp}>{timeAgo(post.created_at)}</Text>
        </View>
      </View>

      <Text style={styles.postText}>{post.text}</Text>

      <View style={styles.footer}>
        <TouchableOpacity style={styles.reactionButton} onPress={handleReact} activeOpacity={0.7}>
          <Text style={[styles.reactionIcon, reacted && styles.reactionIconActive]}>
            {reacted ? '♥' : '♡'}
          </Text>
          <Text style={[styles.reactionLabel, reacted && styles.reactionLabelActive]}>
            {reacted ? 'Reacted' : 'React'}
          </Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 8,
    elevation: 2,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 10,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 10,
  },
  avatarText: {
    color: '#FFFFFF',
    fontWeight: '700',
    fontSize: 16,
  },
  headerText: {
    flex: 1,
  },
  username: {
    fontWeight: '600',
    fontSize: 15,
    color: '#2B2B33',
  },
  timestamp: {
    fontSize: 12,
    color: '#9A9AA5',
    marginTop: 2,
  },
  postText: {
    fontSize: 15,
    lineHeight: 21,
    color: '#3A3A45',
    marginBottom: 12,
  },
  footer: {
    flexDirection: 'row',
    borderTopWidth: 1,
    borderTopColor: '#F0F0F3',
    paddingTop: 10,
  },
  reactionButton: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  reactionIcon: {
    fontSize: 18,
    color: '#9A9AA5',
    marginRight: 6,
  },
  reactionIconActive: {
    color: '#E07A5F',
  },
  reactionLabel: {
    fontSize: 13,
    color: '#9A9AA5',
    fontWeight: '500',
  },
  reactionLabelActive: {
    color: '#E07A5F',
  },
});
