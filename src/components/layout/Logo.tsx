import React from 'react';
import { MantineNumberSize, Text, createStyles, rem } from '@mantine/core';

export default function Logo({ size }: { size?: MantineNumberSize }) {
  const { classes } = useStyles();
  return (
    <div>
      <Text className={classes.title} sx={{ fontSize: size }}>
        <Text component='span' className={classes.highlight}>
          Five
        </Text>
        Days
      </Text>
    </div>
  );
}

const useStyles = createStyles((theme) => ({
  title: {
    fontWeight: 700,
    fontSize: rem(20),
    letterSpacing: rem(-1),
    paddingRight: theme.spacing.md,
    marginBottom: theme.spacing.xs,
    fontFamily: `Greycliff CF, ${theme.fontFamily}`,

    [theme.fn.smallerThan('xs')]: {
      fontSize: rem(28),
    },
  },

  highlight: {
    color: theme.colors.blue[4],
  },
}));
