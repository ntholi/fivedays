import React from 'react';
import { Text, createStyles, rem } from '@mantine/core';

export default function Logo() {
  const { classes } = useStyles();
  return (
    <div>
      <Text className={classes.title}>
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
    paddingLeft: theme.spacing.md,
    paddingRight: theme.spacing.md,
    marginBottom: theme.spacing.xs,
    textAlign: 'center',
    fontFamily: `Greycliff CF, ${theme.fontFamily}`,

    [theme.fn.smallerThan('xs')]: {
      fontSize: rem(28),
    },
  },

  highlight: {
    color: theme.colors.blue[4],
  },
}));
